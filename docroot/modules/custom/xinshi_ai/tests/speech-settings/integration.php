<?php

declare(strict_types=1);

/**
 * @file
 * Exercises settings persistence and upgrade defaults in a disposable Drupal kernel.
 */

use Drupal\Core\Form\FormState;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\xinshi_ai\Controller\SpeechRecognitionController;
use Drupal\xinshi_ai\Form\SettingsForm;
use Drupal\xinshi_ai\Service\HarnessSettings;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

if (getenv('XINSHI_CHAT_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') || dirname(__DIR__, 6) !== '/app') {
  throw new RuntimeException('Use a disposable source-copy container.');
}
$complete = FALSE;
register_shutdown_function(static function () use (&$complete): void { if (!$complete) exit(1); });
$loader = require dirname(__DIR__, 6) . '/vendor/autoload.php';
$module = dirname(__DIR__, 2);
$install = file_get_contents($module . '/xinshi_ai.install');
$defaults = file_get_contents($module . '/config/install/xinshi_ai.settings.yml');
$routes = Yaml::parseFile($module . '/xinshi_ai.routing.yml');
$services = Yaml::parseFile($module . '/xinshi_ai.services.yml')['services'];
rename($module . '/config/install', $module . '/config/fixture-unused');
mkdir($module . '/config/install');
file_put_contents($module . '/config/install/xinshi_ai.settings.yml', $defaults);
file_put_contents($module . '/xinshi_ai.info.yml', Yaml::dump([
  'name' => 'Speech settings fixture', 'type' => 'module', 'core_version_requirement' => '^11',
  'dependencies' => ['drupal:user'],
]));
file_put_contents($module . '/xinshi_ai.module', '<?php');
file_put_contents($module . '/xinshi_ai.install', '<?php');
file_put_contents($module . '/xinshi_ai.routing.yml', Yaml::dump(array_intersect_key($routes,
  array_flip(['xinshi_ai.settings', 'xinshi_ai.speech.status', 'xinshi_ai.speech.transcribe'])), 8));
$services['xinshi_ai.speech']['arguments'][7] = NULL;
file_put_contents($module . '/xinshi_ai.services.yml', Yaml::dump(['services' =>
  array_intersect_key($services, array_flip([
    'logger.channel.xinshi_ai', 'xinshi_ai.speech', 'xinshi_ai.web_search_credentials', 'xinshi_ai.figma_vault',
  ]))], 8));
require dirname(__DIR__, 3) . '/xinshi_chat/tests/install-fixture.php';
\Drupal::service('module_installer')->install(['xinshi_ai']);
file_put_contents('/tmp/speech-update.php', $install);
require '/tmp/speech-update.php';
// Only the unrelated product-document discovery is substituted; configuration is real SQLite.
\Drupal::getContainer()->set('xinshi_ai.product_documents', new class {
  public function availableContentTypes(): array { return []; }
});
$loader->addPsr4('Drupal\\xinshi_ai_runtime\\', dirname(__DIR__, 3) . '/xinshi_ai_runtime/src');
$checks = 0;
function check(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
  $GLOBALS['checks']++;
  echo 'PASS: ' . $message . PHP_EOL;
}
$factory = \Drupal::configFactory();
check($factory->get('xinshi_ai.settings')->get('speech.enabled') === FALSE, 'Install default is a schema-backed false.');
check(\Drupal::service('config.typed')->getDefinition('xinshi_ai.settings')['mapping']['speech']['mapping']['enabled']['type'] === 'boolean', 'The switch has boolean schema.');
$form = SettingsForm::create(\Drupal::getContainer());
$built = $form->buildForm([], new FormState());
check($built['harness']['web_search']['speech_enabled']['#default_value'] === FALSE, 'The administrator sees the disabled default.');
$credential = \Drupal::service('xinshi_ai.web_search_credentials');
$credential->set('synthetic-speech-key');
$factory->getEditable('xinshi_ai.settings')->set('fixture_site_value', 'preserve')->save();
$toolValues = [];
foreach (HarnessSettings::TOOL_GROUPS as $id => $group) {
  $toolValues[$id] = array_combine(array_keys($group['tools']), array_keys($group['tools']));
}
foreach ([TRUE, FALSE, TRUE] as $enabled) {
  $state = (new FormState())->setValues([
    'base_url' => 'https://gateway.test', 'api_key' => 'fixture-key', 'request_timeout' => 180,
    'default_n' => 4, 'worker' => 'daemon', 'ttl' => 300, 'heartbeat_seconds' => 25,
    'max_lifetime_seconds' => 1200,
    'harness' => ['web_search' => ['speech_enabled' => $enabled, 'api_key' => ''],
      'tools' => ['pages_enabled' => FALSE, 'enabled' => $toolValues],
      'service' => ['url' => '', 'producer_id' => 'chat-node'],
      'task_limits' => ['max_model_calls' => 40, 'max_tokens' => 200000]],
  ]);
  $form->validateForm($built, $state);
  check($state->getErrors() === [], 'The existing settings validation accepts the toggle.');
  $form->submitForm($built, $state);
  $factory->reset('xinshi_ai.settings');
  $rebuilt = SettingsForm::create(\Drupal::getContainer())->buildForm([], new FormState());
  check($rebuilt['harness']['web_search']['speech_enabled']['#default_value'] === $enabled, 'Saved choice survives a fresh form and configuration read.');
  check($factory->get('xinshi_ai.settings')->get('harness.tools.disabled') === [], 'Speech toggling leaves the enabled tool list unchanged.');
  check($credential->get() === 'synthetic-speech-key', 'Saving the toggle retains the shared encrypted key.');
  check($factory->get('xinshi_ai.settings')->get('fixture_site_value') === 'preserve', 'Unrelated site configuration survives settings save.');
}
$factory->getEditable('xinshi_ai.settings')->clear('speech')->save();
xinshi_ai_update_10009();
check($factory->get('xinshi_ai.settings')->get('speech.enabled') === FALSE, 'Upgrade defaults a missing switch off.');
foreach ([FALSE, TRUE] as $enabled) {
  $factory->getEditable('xinshi_ai.settings')->set('speech.enabled', $enabled)->save();
  xinshi_ai_update_10009();
  xinshi_ai_update_10009();
  check($factory->get('xinshi_ai.settings')->get('speech.enabled') === $enabled, 'Repeated updates preserve an explicit administrator choice.');
}
$admin = User::load(1);
\Drupal::currentUser()->setAccount($admin);
$client = \Drupal::service('xinshi_ai.speech');
check($client->enabled() && $client->available(), 'The real assembled speech service reads the enabled switch and credential.');
$factory->getEditable('xinshi_ai.settings')->set('speech.enabled', FALSE)->save();
$controller = SpeechRecognitionController::create(\Drupal::getContainer());
$response = $controller->handle(Request::create('/api/v3/ai/speech', 'GET'));
check(json_decode($response->getContent(), TRUE) === ['enabled' => FALSE, 'available' => FALSE], 'The assembled status endpoint reports both disabled flags.');
$request = new class extends Request {
  public function getContent(bool $asResource = FALSE): string|false { throw new RuntimeException('Disabled requests must not consume the body.'); }
};
$request->setMethod('POST');
$response = $controller->handle($request);
check($response->getStatusCode() === 503 && json_decode($response->getContent(), TRUE)['code'] === 'speech_disabled', 'Disabled POST is rejected before consuming audio.');
$normal = User::create(['name' => 'speech-user', 'status' => 1]);
$normal->save();
check(!\Drupal::service('access_manager')->checkNamedRoute('xinshi_ai.settings', [], $normal), 'Ordinary users cannot administer speech settings.');
$role = Role::create(['id' => 'speech_admin', 'label' => 'Speech admin']);
$role->grantPermission('administer xinshi_ai')->save();
$normal->addRole($role->id())->save();
check(\Drupal::service('access_manager')->checkNamedRoute('xinshi_ai.settings', [], $normal), 'The existing AI administration permission grants settings access.');
\Drupal::currentUser()->setAccount(User::getAnonymousUser());
check($controller->handle(Request::create('/api/v3/ai/speech', 'GET'))->getStatusCode() === 403, 'Anonymous status remains rejected.');
echo "Speech settings kernel checks: $checks\n";
$complete = TRUE;
