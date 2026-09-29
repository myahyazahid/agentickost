<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tidak ada koneksi | {{ $portalTenant->name }}</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #faf7f7; color: #111113; }
        main { max-width: 28rem; margin: 0 auto; padding: 3rem 1rem; }
        h1 { font-size: 1.25rem; margin: 0 0 .5rem; }
        p { color: #5f595b; line-height: 1.5; }
        button { min-height: 44px; padding: 0 1rem; border: 1px solid #e8e2e2; border-radius: .375rem; background: #fff; font: inherit; font-weight: 600; }
    </style>
</head>
<body>
    <main>
        <h1>Tidak ada koneksi internet</h1>
        <p>Tagihan dan pembayaran selalu diambil langsung dari server, jadi portal {{ $portalTenant->name }} perlu internet. Coba lagi setelah sinyal kembali.</p>
        <button type="button" onclick="location.reload()">Coba lagi</button>
    </main>
</body>
</html>
