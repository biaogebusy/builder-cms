<?php

namespace Drupal\simple_oauth\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\simple_oauth\Form\Oauth2GenerateKeyForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides controller routines for generating keys.
 *
 * @internal
 */
class Oauth2GenerateKey extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Oauth2GenerateKey constructor.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * Provides an AJAX response for generating keys in a modal.
   */
  public function generateKeysModalAjaxResponse(Request $request) {
    $response = new AjaxResponse();

    $modal_form = call_user_func([
      $this->formBuilder,
      'getForm',
    ], Oauth2GenerateKeyForm::class, $request->query->get('pubk_id'), $request->query->get('pk_id'));

    $response->addCommand(
      new OpenModalDialogCommand(
        'Generate Keys',
        $modal_form,
        ['width' => 'auto']
      )
    );
    $response->addCommand(new InvokeCommand('#key-error-message', 'hide'));

    return $response;
  }

}
