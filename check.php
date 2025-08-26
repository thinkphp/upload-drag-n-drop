<?php
echo "PHP User: " . get_current_user() . "\n";
echo "Process User: " . posix_getpwuid(posix_geteuid())['name'] . "\n";
echo "Current Directory Owner: " . posix_getpwuid(fileowner('.'))['name'] . "\n";
echo "Current Directory Permissions: " . decoct(fileperms('.') & 0777) . "\n";
?>
