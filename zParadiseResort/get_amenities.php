<?php require "include/bootstrap.inc.php"; print_r(db()->query("SELECT name, image_url FROM amenities")->fetchAll(PDO::FETCH_ASSOC));
