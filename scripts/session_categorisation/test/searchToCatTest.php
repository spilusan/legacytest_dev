<?php

require_once '../visits.php';

$o = new ajwp_SearchToCategory('Alfa Laval');

echo $o->viaCategory(); echo "\n";
print_r($o->viaBrand());
print_r($o->viaSupplier());
print_r($o->viaSupplierBranch());
