<?php

include dirname(__FILE__) . DIRECTORY_SEPARATOR . 'AutoloadProration.php';

spl_autoload_register(array('AutoloadProration', 'load'));
