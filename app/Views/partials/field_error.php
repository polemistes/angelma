<?php
/**
 * @var array<string, string> $errors
 * @var string $name
 */
if (isset($errors[$name])): ?>
<span class="field-error"><?= e($errors[$name]) ?></span>
<?php endif;
