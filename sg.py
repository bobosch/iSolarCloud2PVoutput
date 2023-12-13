import base64
import string
import random
from typing import Optional
import time
import json
import sys

import requests
from cryptography.hazmat.primitives import serialization, asymmetric, padding
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from cryptography.hazmat.backends import default_backend


# TODO: Getting these values directly from the files by the Sungrow API is better than hardcoding them...
LOGIN_RSA_PUBLIC_KEY: asymmetric.rsa.RSAPublicKey = serialization.load_pem_public_key(b"-----BEGIN PUBLIC KEY-----\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDJRGV7eyd9peLPOIqFg3oionWqpmrjVik2wyJzWqv8it3yAvo/o4OR40ybrZPHq526k6ngvqHOCNJvhrN7wXNUEIT+PXyLuwfWP04I4EDBS3Bn3LcTMAnGVoIka0f5O6lo3I0YtPWwnyhcQhrHWuTietGC0CNwueI11Juq8NV2nwIDAQAB\n-----END PUBLIC KEY-----")
APP_RSA_PUBLIC_KEY: asymmetric.rsa.RSAPublicKey   = serialization.load_pem_public_key(bytes("-----BEGIN PUBLIC KEY-----\n" + "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCkecphb6vgsBx4LJknKKes-eyj7-RKQ3fikF5B67EObZ3t4moFZyMGuuJPiadYdaxvRqtxyblIlVM7omAasROtKRhtgKwwRxo2a6878qBhTgUVlsqugpI_7ZC9RmO2Rpmr8WzDeAapGANfHN5bVr7G7GYGwIrjvyxMrAVit_oM4wIDAQAB".replace("-", "+").replace("_", "/") + "\n-----END PUBLIC KEY-----",  'utf8'))
ACCESS_KEY = "9grzgbmxdsp3arfmmgq347xjbza4ysps"
APP_KEY = "B0455FBE7AA0328DB57B59AA729F05D8"

def encrypt_rsa(value: str, key: asymmetric.rsa.RSAPublicKey) -> str:
    # Encrypt the value
    encrypted = key.encrypt(
        value.encode(),
        asymmetric.padding.PKCS1v15(),
    )
    return base64.b64encode(encrypted).decode()

def encrypt_aes(data: str, key: str):
    key_bytes = key.encode('utf-8')
    data_bytes = data.encode('utf-8')

    # Ensure the key is 16 bytes (128 bits)
    if len(key_bytes) != 16:
        raise ValueError("Key must be 16 characters long")

    cipher = Cipher(algorithms.AES(key_bytes), modes.ECB(), backend=default_backend())
    encryptor = cipher.encryptor()
    padder = padding.PKCS7(algorithms.AES.block_size).padder()
    padded_data = padder.update(data_bytes) + padder.finalize()
    encrypted_data = encryptor.update(padded_data) + encryptor.finalize()
    return encrypted_data.hex()

def decrypt_aes(data: str, key: str):
    key_bytes = key.encode('utf-8')

    # Ensure the key is 16 bytes (128 bits)
    if len(key_bytes) != 16:
        raise ValueError("Key must be 16 characters long")

    encrypted_data = bytes.fromhex(data)
    cipher = Cipher(algorithms.AES(key_bytes), modes.ECB(), backend=default_backend())
    decryptor = cipher.decryptor()
    decrypted_padded_data = decryptor.update(encrypted_data) + decryptor.finalize()
    unpadder = padding.PKCS7(algorithms.AES.block_size).unpadder()
    decrypted_data = unpadder.update(decrypted_padded_data) + unpadder.finalize()
    return decrypted_data.decode('utf-8')

def generate_random_word(length: int):
    char_pool = string.ascii_letters + string.digits
    random_word = ''.join(random.choice(char_pool) for _ in range(length))
    return random_word

class SungrowScraper:
    def __init__(self, username: str, password: str):
        self.baseUrl = "https://www.isolarcloud.com"
        # TODO: Set the gateway during the login procedure
        self.gatewayUrl = "https://gateway.isolarcloud.eu"
        self.username = username
        self.password = password
        self.session: "requests.Session" = requests.session()
        self.userToken: "str|None" = None

    def login(self):
        self.session = requests.session()
        resp = self.session.post(
            f"{self.baseUrl}/userLoginAction_login",
            data={
                "userAcct": self.username,
                "userPswd": encrypt_rsa(self.password, LOGIN_RSA_PUBLIC_KEY),
            },
            headers={
                "_isMd5": "1"
            },
            timeout=60,
        )
        self.userToken = resp.json()["user_token"]
        return self.userToken

    def post(self, relativeUrl: str, jsn: "Optional[dict]"=None, isFormData=False):
        userToken = self.userToken if self.userToken is not None else self.login()
        jsn = dict(jsn) if jsn is not None else {}
        nonce = generate_random_word(32)
        # TODO: Sungrow also adjusts for time difference between server and client
        # This is probably not a must though. The relevant call is:
        # https://gateway.isolarcloud.eu/v1/timestamp
        unixTimeMs = int(time.time() * 1000)
        jsn["api_key_param"] = {"timestamp": unixTimeMs, "nonce": nonce}
        randomKey = "web" + generate_random_word(13)
        userToken = self.userToken
        userId = userToken.split('_')[0]
        jsn["appkey"] = APP_KEY
        if "token" not in jsn:
            jsn["token"] = userToken
        jsn["sys_code"] = 200
        data: "dict|str"
        if isFormData:
            jsn["api_key_param"] = encrypt_aes(json.dumps(jsn["api_key_param"]), randomKey)
            jsn["appkey"] = encrypt_aes(jsn["appkey"], randomKey)
            jsn["token"] = encrypt_aes(jsn["token"], randomKey)
            data = jsn
        else:
            data = encrypt_aes(json.dumps(jsn, separators=(",", ":")), randomKey)
        resp = self.session.post(
            f"{self.gatewayUrl}{relativeUrl}",
            data=data,
            headers={
                "x-access-key": ACCESS_KEY,
                "x-random-secret-key": encrypt_rsa(randomKey, APP_RSA_PUBLIC_KEY),
                "x-limit-obj": encrypt_rsa(userId, APP_RSA_PUBLIC_KEY),
                "content-type": "application/json;charset=UTF-8"
            }
        )
        return decrypt_aes(resp.text, randomKey)

s = SungrowScraper("USERNAME", "PASSWORD")
resp = s.post(
    "/v1/commonService/queryMutiPointDataList",
    jsn = eval(sys.argv[1])
)
print(resp)
