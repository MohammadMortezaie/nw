(function ($) {
  'use strict';

  function reindexRows($repeater) {
    var name = $repeater.data('name');
    if (!name) return;

    $repeater.find('.nw-repeater__rows > .nw-repeater__row').each(function (index) {
      var $row = $(this);
      $row.attr('data-index', index);
      $row.find('.nw-repeater__row-title').each(function () {
        var base = $(this).data('label') || 'Item';
        $(this).text(base + ' ' + (index + 1));
      });
      $row.find('[name]').each(function () {
        var $field = $(this);
        var fieldName = $field.attr('name');
        if (!fieldName) return;
        // nw_detail_sections[0][title] -> nw_detail_sections[index][title]
        fieldName = fieldName.replace(new RegExp('^' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\[\\d+\\]'), name + '[' + index + ']');
        $field.attr('name', fieldName);
      });
    });
  }

  function bindMedia($scope) {
    $scope.find('[data-nw-media]').off('click.nwMedia').on('click.nwMedia', function (e) {
      e.preventDefault();
      var $btn = $(this);
      var $wrap = $btn.closest('.nw-media-field');
      var $input = $wrap.find('input[type="url"]');
      var $preview = $wrap.find('.nw-media-field__preview');
      var frame = wp.media({
        title: $btn.data('title') || 'Select image',
        button: { text: 'Use image' },
        multiple: false
      });
      frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();
        var url = attachment.url || '';
        $input.val(url).trigger('change');
        if ($preview.length && url) {
          $preview.attr('src', url).addClass('is-visible');
        }
      });
      frame.open();
    });

    $scope.find('[data-nw-media-clear]').off('click.nwMediaClear').on('click.nwMediaClear', function (e) {
      e.preventDefault();
      var $wrap = $(this).closest('.nw-media-field');
      $wrap.find('input[type="url"]').val('').trigger('change');
      $wrap.find('.nw-media-field__preview').removeClass('is-visible').attr('src', '');
    });

    $scope.find('[data-nw-media-id]').off('click.nwMediaId').on('click.nwMediaId', function (e) {
      e.preventDefault();
      var $btn = $(this);
      var $wrap = $btn.closest('.nw-media-field');
      var $input = $wrap.find('input[type="hidden"]');
      var $preview = $wrap.find('.nw-media-field__preview');
      var frame = wp.media({
        title: $btn.data('title') || 'Select image',
        button: { text: 'Use image' },
        library: { type: 'image' },
        multiple: false
      });
      frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();
        $input.val(attachment.id || '').trigger('change');
        var url = (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) || attachment.url || '';
        if ($preview.length && url) {
          $preview.attr('src', url).addClass('is-visible');
        }
      });
      frame.open();
    });

    $scope.find('[data-nw-media-id-clear]').off('click.nwMediaIdClear').on('click.nwMediaIdClear', function (e) {
      e.preventDefault();
      var $wrap = $(this).closest('.nw-media-field');
      $wrap.find('input[type="hidden"]').val('').trigger('change');
      $wrap.find('.nw-media-field__preview').removeClass('is-visible').attr('src', '');
    });

    $scope.find('[data-nw-media-file]').off('click.nwMediaFile').on('click.nwMediaFile', function (e) {
      e.preventDefault();
      var $btn = $(this);
      var $wrap = $btn.closest('.nw-media-field');
      var $input = $wrap.find('input[type="hidden"]');
      var $name = $wrap.find('[data-nw-file-name]');
      var frame = wp.media({
        title: $btn.data('title') || 'Select PDF',
        button: { text: 'Use PDF' },
        library: { type: 'application/pdf' },
        multiple: false
      });
      frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();
        $input.val(attachment.id || '').trigger('change');
        if ($name.length) {
          $name.text(attachment.filename || attachment.title || 'PDF selected');
        }
      });
      frame.open();
    });

    $scope.find('[data-nw-media-file-clear]').off('click.nwMediaFileClear').on('click.nwMediaFileClear', function (e) {
      e.preventDefault();
      var $wrap = $(this).closest('.nw-media-field');
      $wrap.find('input[type="hidden"]').val('').trigger('change');
      $wrap.find('[data-nw-file-name]').text('No file selected.');
    });
  }

  $(function () {
    bindMedia($(document));

    function syncGalleryType() {
      var type = $('input[name="nw_gallery_type"]:checked').val() || 'image';
      $('[data-nw-gallery-panel]').each(function () {
        this.hidden = $(this).data('nw-gallery-panel') !== type;
      });
    }
    $(document).on('change', '[data-nw-gallery-type]', syncGalleryType);
    if ($('[data-nw-gallery-type]').length) {
      syncGalleryType();
    }

    $(document).on('click', '[data-nw-repeater-add]', function (e) {
      e.preventDefault();
      var $btn = $(this);
      var target = $btn.data('nw-repeater-add');
      var $repeater = $('[data-nw-repeater="' + target + '"]');
      var templateId = $btn.data('template');
      var $template = $('#' + templateId);
      if (!$repeater.length || !$template.length) return;

      var index = $repeater.find('.nw-repeater__rows > .nw-repeater__row').length;
      var html = $template.html().replace(/__INDEX__/g, String(index));
      var $row = $(html);
      $repeater.find('.nw-repeater__rows').append($row);
      $repeater.find('.nw-admin-empty').hide();
      bindMedia($row);
      reindexRows($repeater);
    });

    $(document).on('click', '[data-nw-repeater-remove]', function (e) {
      e.preventDefault();
      var $row = $(this).closest('.nw-repeater__row');
      var $repeater = $row.closest('[data-nw-repeater]');
      $row.remove();
      if (!$repeater.find('.nw-repeater__rows > .nw-repeater__row').length) {
        $repeater.find('.nw-admin-empty').show();
      }
      reindexRows($repeater);
    });

    $(document).on('click', '[data-nw-repeater-move]', function (e) {
      e.preventDefault();
      var dir = $(this).data('nw-repeater-move');
      var $row = $(this).closest('.nw-repeater__row');
      if (dir === 'up') {
        $row.prev('.nw-repeater__row').before($row);
      } else {
        $row.next('.nw-repeater__row').after($row);
      }
      reindexRows($row.closest('[data-nw-repeater]'));
    });

    function productPhotoCount($wrap) {
      return $wrap.find('.nw-product-photos__list > .nw-product-photos__item').length;
    }

    function syncProductPhotos($wrap) {
      var max = parseInt($wrap.data('max'), 10) || 5;
      var count = productPhotoCount($wrap);
      $wrap.find('[data-nw-product-photos-add]').prop('disabled', count >= max);
      $wrap.find('.nw-admin-empty').toggle(count === 0);
      var $count = $wrap.find('[data-nw-product-photos-count]');
      if ($count.length) {
        $count.text(String(count) + ' of ' + String(max) + ' photos');
      }
    }

    $(document).on('click', '[data-nw-product-photos-add]', function (e) {
      e.preventDefault();
      var $wrap = $(this).closest('[data-nw-product-photos]');
      var max = parseInt($wrap.data('max'), 10) || 5;
      var remain = max - productPhotoCount($wrap);
      if (remain <= 0 || typeof wp === 'undefined' || !wp.media) return;

      var frame = wp.media({
        title: 'Select product photos',
        button: { text: 'Use photos' },
        library: { type: 'image' },
        multiple: true
      });
      frame.on('select', function () {
        var $template = $('#nw-tpl-product-photo');
        if (!$template.length) return;
        frame.state().get('selection').each(function (attachment) {
          if (productPhotoCount($wrap) >= max) return;
          var json = attachment.toJSON();
          var id = String(json.id || '');
          if (!id || $wrap.find('input[name="nw_product_photo_ids[]"][value="' + id + '"]').length) return;
          var url = (json.sizes && json.sizes.medium && json.sizes.medium.url) || json.url || '';
          var html = $template.html().replace(/__ID__/g, id).replace(/__URL__/g, url);
          $wrap.find('.nw-product-photos__list').append(html);
        });
        syncProductPhotos($wrap);
      });
      frame.open();
    });

    $(document).on('click', '[data-nw-product-photos-remove]', function (e) {
      e.preventDefault();
      var $wrap = $(this).closest('[data-nw-product-photos]');
      $(this).closest('.nw-product-photos__item').remove();
      syncProductPhotos($wrap);
    });

    $(document).on('click', '[data-nw-product-photos-move]', function (e) {
      e.preventDefault();
      var dir = $(this).data('nw-product-photos-move');
      var $item = $(this).closest('.nw-product-photos__item');
      if (dir === 'up') {
        $item.prev('.nw-product-photos__item').before($item);
      } else {
        $item.next('.nw-product-photos__item').after($item);
      }
    });
  });
})(jQuery);
