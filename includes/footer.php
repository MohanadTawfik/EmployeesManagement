<?php if (isset($_SESSION['user_id']) && !isset($custom_layout)): ?>
    </div>
<?php endif; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/js/bootstrap-datepicker.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/locales/bootstrap-datepicker.ar.min.js"></script>
<script src="assets/js/app.js"></script>

<?php if (isset($extra_js)): ?>
    <?php foreach ($extra_js as $js_file): ?>
        <script src="<?php echo $js_file; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

</body>

</html>