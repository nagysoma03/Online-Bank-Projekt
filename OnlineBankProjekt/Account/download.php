<?php
$file = 'img.png'; // Itt kell megadni a helyes fájl elérési utat

if (file_exists($file)) {
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    readfile($file); // A fájl tartalmát átküldi a böngészőnek
    exit;
} else {
    echo "A fájl nem található.";
}
