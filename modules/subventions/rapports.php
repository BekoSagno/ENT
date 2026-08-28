<?php
// Redirection — ancien rapport DGPEIP remplacé par RDB
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
redirect('/modules/subventions/rdb/index.php');
