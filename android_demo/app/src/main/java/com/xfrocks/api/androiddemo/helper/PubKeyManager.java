package com.xfrocks.api.androiddemo.helper;

import java.math.BigInteger;
import java.security.cert.CertificateException;
import java.security.cert.X509Certificate;
import java.security.interfaces.RSAPublicKey;

import javax.net.ssl.X509TrustManager;

public final class PubKeyManager implements X509TrustManager {

    private String publicKey;

    public PubKeyManager(String publicKey) {
        this.publicKey = publicKey;
    }

    @Override
    public void checkClientTrusted(X509Certificate[] chain, String authType) throws CertificateException {
        _isTrusted(chain);
    }

    @Override
    public void checkServerTrusted(X509Certificate[] chain, String authType) throws CertificateException {
        _isTrusted(chain);
    }

    @Override
    public X509Certificate[] getAcceptedIssuers() {
        return new X509Certificate[0];
    }

    protected void _isTrusted(X509Certificate[] chain) throws CertificateException {
        // https://medium.com/@faruktoptas/android-security-tip-public-key-pinning-with-volley-library-fb85bf761857
        // this method has been simplified to bypass all CA check
        // and rely solely on public key verification

        if (chain == null) {
            throw new IllegalArgumentException("Certificate chain is null");
        }
        if (!(chain.length > 0)) {
            throw new IllegalArgumentException("No certificate");
        }

        RSAPublicKey certPublicKey = (RSAPublicKey) chain[0].getPublicKey();
        String encoded = new BigInteger(1, certPublicKey.getEncoded()).toString(16);
        if (!publicKey.equalsIgnoreCase(encoded)) {
            throw new CertificateException("Invalid certificate");
        }
    }
}