/**
 * @file
 * Loads embedded content (videos, maps, podcasts) only after the user agreed.
 *
 * The content is loaded on a click on the consent button or immediately, if the user already agreed to external
 * content in the cookie banner (EU Cookie Compliance). Without the cookie banner, the button is still working.
 */
(function ($, Drupal, once) {
  'use strict';

  /**
   * Checks, if the user agreed to external content in the cookie banner.
   *
   * @return {boolean}
   *   True, if the user agreed.
   */
  function hasAgreedToExternalContent() {
    return typeof Drupal.eu_cookie_compliance !== 'undefined'
      && typeof Drupal.eu_cookie_compliance.hasAgreed === 'function'
      && Drupal.eu_cookie_compliance.hasAgreed('external');
  }

  /**
   * Replaces the placeholder by the content and reactivates the video container.
   *
   * @param {jQuery} placeholder
   *   The placeholder with the consent button.
   * @param {jQuery|string} content
   *   The content to show instead of the placeholder.
   */
  function showContent(placeholder, content) {
    const container = placeholder.parent();
    if (container.hasClass('video-container-inactive')) {
      container.addClass('video-container').removeClass('video-container-inactive');
    }
    placeholder.replaceWith(content);
  }

  /**
   * Binds the consent button and loads the content, if the user already agreed.
   *
   * @param {HTMLElement} button
   *   The consent button.
   * @param {function} buildContent
   *   Returns the content for the placeholder.
   */
  function initConsentButton(button, buildContent) {
    const $button = $(button);
    $button.on('click', function () {
      const placeholder = $button.parent();
      showContent(placeholder, buildContent(placeholder));
    });
    if (hasAgreedToExternalContent()) {
      $button.trigger('click');
    }
  }

  Drupal.behaviors.blaetterEmbed = {
    attach: function (context) {
      // Video containers with a placeholder must not keep their aspect ratio until the content is loaded.
      once('blaetter-embed-container', '.video-container', context).forEach(function (container) {
        const $container = $(container);
        if ($container.has('.blaetter-embed').length) {
          $container.removeClass('video-container').addClass('video-container-inactive');
        }
      });

      // Media (remote videos, generic iframes): the content is stored as JSON in data-content.
      once('blaetter-media-consent', 'button.blaetter-media-consent', context).forEach(function (button) {
        initConsentButton(button, function (placeholder) {
          return JSON.parse(placeholder.attr('data-content'));
        });
      });

      // Embed block: the iframe is built from the data attributes.
      once('blaetter-embed-consent', 'button.blaetter-embed-consent', context).forEach(function (button) {
        initConsentButton(button, function (placeholder) {
          return $('<iframe></iframe>').attr({
            src: placeholder.attr('data-embedurl'),
            title: placeholder.attr('data-title'),
            style: 'width:100%;' + (placeholder.attr('data-styles') || ''),
            frameborder: '0',
            height: 'auto',
            scrolling: 'no'
          });
        });
      });
    }
  };
})(jQuery, Drupal, once);
