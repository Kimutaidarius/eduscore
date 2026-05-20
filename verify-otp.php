<form method="POST" action="ajax/verify-otp.php">
    <input type="text" name="otp" maxlength="6" required>
    <input type="hidden" name="email" value="<?= htmlspecialchars($_GET['email']) ?>">
    <button type="submit">Verify</button>
</form>
