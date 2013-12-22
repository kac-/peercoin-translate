<?php
$INI_PATH="i18n-example.ini";
require_once("i18n.inc.php");
$lang = "de";
$i = new i18n("../trans","peercoin.net_p");
$i->echolocalized("@logo.slogan0");echo "<br/>";
$i->echolocalized("@motto1");echo "<br/>";
$i->echolocalized("@motto2");echo "<br/>";

?>
