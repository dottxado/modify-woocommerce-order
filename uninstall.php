<?php

use Dottxado\ModifyWooOrder\AdminPanel;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	return;
}
require_once __DIR__ . '/vendor/autoload.php';

delete_option( AdminPanel::OPTION_NAME );
