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
    }

    /**
     * {@inheritdoc}
     */
    public function build()
    {
        $socialmedia = [];
        $socialmedia['provider']     = $this->configuration['socialmedia_provider'];

        $build = [
            '#theme'        => 'blaetter_socialmedia_block',
            '#socialmedia'  => $socialmedia,
        ];

        return $build;
    }
}
