<!DOCTYPE html>
<html>
<head>
    <title>Test PayTabs</title>
</head>
<body>
    <h1>Payer pour publier une annonce</h1>
    <form action="{{ route('paytabs.pay') }}" method="GET">
        <input type="number" name="amount" placeholder="Montant" value="100" required>
        <button type="submit">Payer maintenant</button>
    </form>
</body>
</html>
