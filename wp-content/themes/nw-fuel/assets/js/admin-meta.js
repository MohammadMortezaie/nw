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

  function apiManagedBadge() {
    return '<span class="nw-api-managed-badge">Updated every day by API</span>';
  }

  function markProductApiFields() {
    if (!$('body').hasClass('post-type-product')) return;
    if (!$('[data-nw-api-managed="1"]').length) return;

    var $titleWrap = $('#titlewrap');
    if ($titleWrap.length && !$titleWrap.children('.nw-api-managed-title').length) {
      $titleWrap.prepend('<div class="nw-api-managed-title">' + apiManagedBadge() + '</div>');
    }

    var selectors = [
      '#product_catdiv .postbox-header h2',
      '#product_catdiv > h2.hndle',
      'label[for="_regular_price"]',
      'label[for="_manage_stock"]',
      'label[for="_stock"]',
      'label[for="_stock_status"]',
      'fieldset._stock_status_field > legend',
      '#catalog-visibility'
    ];

    $(selectors.join(',')).each(function () {
      var $target = $(this);
      if (!$target.children('.nw-api-managed-badge').length) {
        $target.append(apiManagedBadge());
      }
    });
  }

  function bindRelatedProductPicker($scope) {
    $scope.find('[data-nw-related-picker]').each(function () {
      var $picker = $(this);
      var $search = $picker.find('[data-nw-related-search]');
      var $results = $picker.find('[data-nw-related-results]');
      var $selected = $picker.find('[data-nw-related-selected]');
      var $value = $picker.find('[data-nw-related-value]');
      var $empty = $picker.find('[data-nw-related-empty]');
      var $status = $picker.find('[data-nw-related-status]');
      var $spinner = $picker.find('[data-nw-related-spinner]');
      var timer = null;
      var request = null;
      var searchSequence = 0;

      function selectedHas(slug) {
        return $selected.find('[data-related-slug]').filter(function () {
          return $(this).attr('data-related-slug') === slug;
        }).length > 0;
      }

      function closeResults() {
        $results.empty().prop('hidden', true);
      }

      function syncSelectedValue() {
        var slugs = $selected.children('[data-related-slug]').map(function () {
          return $(this).attr('data-related-slug') || '';
        }).get().filter(Boolean);
        $value.val(JSON.stringify(slugs));
      }

      function addSelectedProduct(product) {
        if (!product.slug || selectedHas(product.slug)) return;

        var $item = $('<div>', {
          class: 'nw-related-picker__item',
          'data-related-slug': product.slug
        });

        if (product.image) {
          $('<img>', {
            class: 'nw-related-picker__thumb',
            src: product.image,
            alt: '',
            loading: 'lazy'
          }).appendTo($item);
        }

        var $copy = $('<span>', { class: 'nw-related-picker__item-copy' }).appendTo($item);
        $('<strong>').text(product.title || product.slug).appendTo($copy);
        if (product.part) {
          $('<code>').text(product.part).appendTo($copy);
        }
        $('<button>', {
          type: 'button',
          class: 'button-link-delete',
          'data-nw-related-remove': '',
          text: 'Remove'
        }).appendTo($item);

        $selected.append($item);
        syncSelectedValue();
        $empty.hide();
        $search.val('').trigger('focus');
        $status.text('Product added.');
        closeResults();
      }

      function renderResults(products) {
        closeResults();
        products = products.filter(function (product) {
          return product.slug && !selectedHas(product.slug);
        });

        if (!products.length) {
          $status.text('No matching products found.');
          return;
        }

        products.forEach(function (product) {
          var $button = $('<button>', {
            type: 'button',
            class: 'nw-related-picker__result',
            'data-related-result': '',
            'data-related-slug': product.slug,
            'data-related-title': product.title || product.slug,
            'data-related-part': product.part || '',
            'data-related-image': product.image || ''
          });
          if (product.image) {
            $('<img>', {
              class: 'nw-related-picker__thumb',
              src: product.image,
              alt: '',
              loading: 'lazy'
            }).appendTo($button);
          }
          var $resultCopy = $('<span>', { class: 'nw-related-picker__result-copy' }).appendTo($button);
          $('<strong>').text(product.title || product.slug).appendTo($resultCopy);
          $('<span>').text(product.part ? 'Part # ' + product.part : product.slug).appendTo($resultCopy);
          $results.append($button);
        });
        $results.prop('hidden', false);
        $status.text(products.length + ' product' + (products.length === 1 ? '' : 's') + ' found.');
      }

      $search.on('input', function () {
        var query = $.trim($search.val());
        var sequence = ++searchSequence;
        window.clearTimeout(timer);
        if (request) {
          request.abort();
          request = null;
        }
        $spinner.removeClass('is-active');

        if (query.length < 2) {
          closeResults();
          $status.text(query.length ? 'Type at least 2 characters.' : '');
          return;
        }

        timer = window.setTimeout(function () {
          $spinner.addClass('is-active');
          $status.text('Searching…');
          var activeRequest = $.get(window.ajaxurl, {
            action: 'nw_fuel_search_related_products',
            nonce: $picker.data('nonce'),
            product_id: $picker.data('product-id'),
            q: query
          });
          request = activeRequest;
          activeRequest.done(function (response) {
            if (sequence !== searchSequence) return;
            renderResults(response && response.success && Array.isArray(response.data) ? response.data : []);
          }).fail(function (_xhr, status) {
            if (status !== 'abort' && sequence === searchSequence) {
              closeResults();
              $status.text('Search failed. Please try again.');
            }
          }).always(function () {
            if (request === activeRequest) {
              request = null;
            }
            if (sequence === searchSequence) {
              $spinner.removeClass('is-active');
            }
          });
        }, 250);
      });

      $results.on('click', '[data-related-result]', function () {
        var $result = $(this);
        addSelectedProduct({
          slug: $result.attr('data-related-slug') || '',
          title: $result.attr('data-related-title') || '',
          part: $result.attr('data-related-part') || '',
          image: $result.attr('data-related-image') || ''
        });
      });

      $selected.on('click', '[data-nw-related-remove]', function () {
        $(this).closest('[data-related-slug]').remove();
        syncSelectedValue();
        $empty.toggle($selected.children('[data-related-slug]').length === 0);
        $status.text('Product removed. Save or update the product to keep this change.');
      });
    });
  }

  $(function () {
    markProductApiFields();
    bindMedia($(document));
    bindRelatedProductPicker($(document));

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
