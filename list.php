<?php
/**
 * db       .d8b.  db   d8b   db d8888b. d88888b d8b   db  .o88b. d88888b
 * 88      d8' `8b 88   I8I   88 88  `8D 88'     888o  88 d8P  Y8 88'
 * 88      88ooo88 88   I8I   88 88oobY' 88ooooo 88V8o 88 8P      88ooooo
 * 88      88~~~88 Y8   I8I   88 88`8b   88~~~~~ 88 V8o88 8b      88~~~~~
 * 88booo. 88   88 `8b d8'8b d8' 88 `88. 88.     88  V888 Y8b  d8 88.
 * Y88888P YP   YP  `8b8' `8d8'  88   YD Y88888P VP   V8P  `Y88P' Y88888P
 *
 * Time: 7/4/2017 17:48
 * File Name: list.php
 * Description: Exclusion list user interface
 */
// Check if we are a user
OCP\User::checkLoggedIn();

$tmpl = new OCP\Template('nextant', 'list', '');
OCP\Util::addScript('nextant', 'exclusion');
$tmpl->printPage();