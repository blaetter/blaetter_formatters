<?php

namespace Drupal\blaetter_formatters\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'SocialMedia' block.
 *
 * @Block(
 *  id = "socialmedia_block",
 *  admin_label = @Translation("SocialMedia block"),
 * )
 */
class SocialMediaBlock extends BlockBase
{

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

        $form['socialmedia_settings'] = [
            '#type'         => 'details',
            '#open'         => true,
            '#title'        => $this->t('Settings for the socialmedia block'),
            '#weight'       => '20',
        ];
        $form['socialmedia_settings']['socialmedia_provider'] = [
            '#type'         => 'checkboxes',
            '#title'        => $this->t('Socialmedia provider'),
            '#description'  => $this->t('choose, which social media providers should be displayed.'),
            '#default_value' => $this->configuration['socialmedia_provider'],
            '#options'      => [
              'twitter'       => 'Twitter',
              'facebook'      => 'Facebook',
              'instagram'     => 'Instagram',
              'youtube'       => 'YouTube',
              'newsletter'    => 'Newsletter',
              'rss'           => 'RSS',
            ],
            '#required'     => true,
        ];
        $form['socialmedia_settings']['block_layout'] = [
          '#type' => 'select',
          '#title' => $this->t('Box Layout'),
          '#description' => $this->t('Select desired box layout option.'),
          '#options' => [
              'block' => $this->t('transparent background'),
              'block block--white' => $this->t('white background')],
          '#default_value' => $this->configuration['block_layout'] ?? '',
          '#size' => 1,
          '#weight' => '20',
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function blockSubmit($form, FormStateInterface $form_state)
    {
        $this->configuration['socialmedia_provider'] = $form_state->getValue(
            [
              'socialmedia_settings',
              'socialmedia_provider'
            ]
        );
        $this->configuration['block_layout'] = $form_state->getValue(
            [
              'socialmedia_settings',
              'block_layout'
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function build()
    {
        $socialmedia = [];
        $socialmedia['provider']     = $this->configuration['socialmedia_provider'];
        $socialmedia['block_layout'] = $this->configuration['block_layout'];

        $build = [
            '#theme'        => 'blaetter_socialmedia_block',
            '#socialmedia'  => $socialmedia,
        ];

        return $build;
    }
}
