<?php

namespace Drupal\blaetter_formatters\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'EmbedBlock' block.
 *
 * @Block(
 *  id = "embed_block",
 *  admin_label = @Translation("Embed block"),
 * )
 */
class EmbedBlock extends BlockBase
{

    private $provider = [
      'youtube'   => 'Youtube',
      'vimeo'     => 'Vimeo',
      'detektor'  => 'detektor.fm',
      'gmap'      => 'Google Maps',
    ];

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        return [] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function blockForm($form, FormStateInterface $form_state)
    {

        $form['embed_settings'] = [
            '#type'         => 'details',
            '#open'         => true,
            '#title'        => $this->t('Settings for the embed block'),
            '#weight'       => '20',
        ];
        $form['embed_settings']['embed_url'] = [
            '#type'         => 'textfield',
            '#title'        => $this->t('url'),
            '#description'  => $this->t('The url of the embed'),
            '#default_value' => $this->configuration['embed_url'],
            '#maxlength'    => 2048,
            '#size'         => 64,
            '#weight'       => '10',
            '#required'     => true,
        ];
        $form['embed_settings']['provider'] = [
            '#type'         => 'select',
            '#title'        => $this->t('Provider'),
            '#description'  => $this->t(
                'Select the provider for the embed. This is used for technical reasons and if the privacy requires ' .
                'a message to the user.'
            ),
            '#options'      => [
                ''          => $this->t('-- please choose --'),
                'youtube'   => $this->provider['youtube'],
                'vimeo'     => $this->provider['vimeo'],
                'detektor'  => $this->provider['detektor'],
                'gmap'      => $this->provider['gmap'],
            ],
            '#default_value' => $this->configuration['provider'],
            '#size'         => 1,
            '#weight'       => '20',
            '#required'     => true,
        ];
        $form['embed_settings']['privacy'] = [
            '#type'         => 'select',
            '#title'        => $this->t('Privacy'),
            '#description'  => $this->t('Select if the content of the embed is using private data of the user.'),
            '#options'      => [
                ''  => $this->t('-- please choose --'),
                'y' => $this->t('yes, the embed provider processes user data for own reasons.'),
                'n' => $this->t('no, the embed provider does not process user data for own reasons.'),
            ],
            '#default_value' => $this->configuration['privacy'],
            '#size'         => 1,
            '#weight'       => '30',
            '#required'     => true,
        ];
        $form['embed_settings']['additional_styles'] = [
          '#type'         => 'textfield',
          '#title'        => $this->t('additional styles'),
          '#description'  => $this->t('Please add further styles wich will be placed within the iframes styles.'),
          '#default_value' => $this->configuration['additional_styles'],
          '#maxlength'    => 256,
          '#size'         => 64,
          '#weight'       => '40',
          '#required'     => true,
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function blockSubmit($form, FormStateInterface $form_state)
    {
        $this->configuration['embed_url'] = $form_state->getValue(['embed_settings', 'embed_url']);
        $this->configuration['provider'] = $form_state->getValue(['embed_settings', 'provider']);
        $this->configuration['privacy'] = $form_state->getValue(['embed_settings', 'privacy']);
        $this->configuration['additional_styles'] = $form_state->getValue(['embed_settings', 'additional_styles']);
    }

    /**
     * {@inheritdoc}
     */
    public function build()
    {
        $embed = [];
        $embed['title']     = $this->configuration['label'];
        $embed['url']       = $this->configuration['embed_url'];
        $embed['privacy']   = $this->configuration['privacy'];
        $embed['provider']  = $this->provider[
          $this->configuration['provider']
        ];
        $embed['provider_slug'] = $this->configuration['provider'];
        $embed['styles']    = $this->configuration['additional_styles'];

        $build = [
            '#theme'        => 'blaetter_embed_block',
            '#embed'        => $embed,
            '#attached' => [
              'library' => [
                'blaetter_formatters/embed_block',
              ],
            ],
        ];

        return $build;
    }
}
