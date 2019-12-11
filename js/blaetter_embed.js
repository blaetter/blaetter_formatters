

jQuery(document).ready(function () {
  if (jQuery(".video-container").length > 0) {
    jQuery(".video-container").has('.blaetter-embed').each(function(index) {
      jQuery(this).removeClass('video-container');
      jQuery(this).addClass('video-container-inactive');
    })
  }

  if (jQuery("button.blaetter-media-consent").length > 0) {
    jQuery("button.blaetter-media-consent").each(function(index) {
      jQuery(this).click(function(index) {
        var parent = jQuery(this).parent();
        var iframe = JSON.parse(parent.attr('data-content'));
        if (parent.parent().hasClass('video-container-inactive')) {
          parent.parent().addClass('video-container');
          parent.parent().removeClass('video-container-inactive');
        }
        parent.replaceWith(iframe);
      })
    });
  }

  if (jQuery("button.blaetter-embed-consent").length > 0) {
    jQuery("button.blaetter-embed-consent").each(function(index) {
      jQuery(this).click(function(index) {
        var parent = jQuery(this).parent();
        var iframe = jQuery('<iframe></iframe>');
        iframe.attr('src', parent.attr('data-embedurl'));
        iframe.attr('title', parent.attr('data-title'));
        iframe.attr('style', 'width:100%;' + parent.attr('data-styles'));
        iframe.attr('frameborder', '0');
        iframe.attr('height', 'auto');
        iframe.attr('scrolling', 'no');
        if (parent.parent().hasClass('video-container-inactive')) {
          parent.parent().addClass('video-container');
          parent.parent().removeClass('video-container-inactive');
        }
        parent.replaceWith(iframe);
      })
    });
  }

});
