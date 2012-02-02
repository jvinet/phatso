<p>Hello, World!</p>

<p>If you passed any inline parameters, they'll be shown here:</p>
<?php debug($params) ?>

<p>If you passed any GET/POST parameters, they'll be shown here:</p>
<?php debug(array_merge($_GET,$_POST)) ?>
