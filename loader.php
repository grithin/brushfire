<?
/// logic unrelated to a specific request
/** @file */

#Tool, used by config
require_once $_ENV['systemFolder'].'tool/Tool.php';

#used by autoloader
require_once $_ENV['systemFolder'].'tool/Arrays.php';
require_once $_ENV['systemFolder'].'tool/Hook.php';
require_once $_ENV['systemFolder'].'tool/CommonTraits.php';

#Config setting
require_once $_ENV['systemFolder'].'tool/Config.php';
Config::init();

#Autoloader
require_once $_ENV['systemFolder'].'tool/Autoload.php';
$autoload = Autoload::init(null,$_ENV['autoloadIncludes']);
spl_autoload_register(array($autoload,'auto'));
#composer autload
if(is_file($_ENV['composerFolder'].'autoload.php')){
	require_once $_ENV['composerFolder'].'autoload.php';
}

set_error_handler($_ENV['errorHandler'],$_ENV['errorsHandled']);
set_exception_handler($_ENV['exceptionHandler']);

Config::loadUserFiles($_ENV['preRoute']);

#pre session request handling; for file serving and such.
require_once $_ENV['systemFolder'].'tool/control/Route.php';
\control\Route::handle($_SERVER['REQUEST_URI']);