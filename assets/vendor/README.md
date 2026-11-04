# Vendor-Bibliotheken

Die Dateien in diesem Ordner sind Drittanbieter-Bibliotheken, die lokal
eingebunden werden (kein CDN zur Laufzeit). Sie sind per `.gitignore` bewusst
**nicht** im Repository enthalten, damit keine fremden Minified-Dateien im
eigenen Code-Verlauf landen. Vor dem ersten Start einmalig hier ablegen:

## bootstrap.min.css

- Bootstrap 5.3.3, CSS, MIT-Lizenz — https://getbootstrap.com/
- Quelle: `https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css`
- Download:
  ```bash
  curl -o bootstrap.min.css "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
  ```

## qrcode.min.js

- `qrcode-generator` von Kazuhiko Arase, MIT-Lizenz —
  https://github.com/kazuhikoarase/qrcode-generator
- Quelle: `https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js`
- Download:
  ```bash
  curl -o qrcode.min.js "https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"
  ```

Beide Dateien werden von der Anwendung ausschließlich vom eigenen Server
ausgeliefert; der externe Download ist nur ein einmaliger Einrichtungsschritt.
