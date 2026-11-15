<?php if (current_user() && current_user()['role']==='admin'): ?>
</main></div></div>
<footer class="container-fluid py-4 text-center text-secondary small">EventGrid 3.0 · College events, connected.</footer>
<?php else: ?>
</main>
<footer class="container py-4 text-center text-secondary small">EventGrid 3.0 · College events, connected.</footer>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<?php if (current_user() && current_user()['role']==='student'): ?><script src="<?= e(url('chatbot/chatbot.js')) ?>"></script><?php endif; ?>
</body></html>
