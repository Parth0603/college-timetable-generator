$(function () {
  $(document).on('click', '.sidebar-toggle', function () {
    $('body').toggleClass('sidebar-open');
  });

  $(document).on('submit', '[data-confirm]', function (event) {
    if (!window.confirm($(this).data('confirm'))) {
      event.preventDefault();
    }
  });

  $(document).on('click', '.toggle-availability', function () {
    const checkbox = $(this);
    $.post(window.location.href, {
      csrf_token: $('meta[name="csrf-token"]').attr('content'),
      action: 'toggle',
      availability_id: checkbox.data('id'),
      is_available: checkbox.prop('checked') ? 1 : 0
    }).fail(function () {
      checkbox.prop('checked', !checkbox.prop('checked'));
      window.alert('Could not update availability.');
    });
  });
});
