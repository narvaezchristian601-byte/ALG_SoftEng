// js/pagination.js
// Requires jQuery (local copy) loaded before this file.
//
// Usage:
// - schedule.php and project_logs.php call the appropriate endpoint
//   with ?page=N via loadList(endpoint, containerSelector)

function loadList(endpoint, page, containerSelector) {
  page = page || 1;
  const $container = $(containerSelector);
  $container.html('<div style="padding:20px;text-align:center;">Loading…</div>');
  $.get(endpoint, { page: page })
    .done(function (resp) {
      // Expect JSON: { rowsHtml: "...", paginationHtml: "..." }
      if (resp && resp.rowsHtml !== undefined) {
        $container.html(resp.rowsHtml);
        $(containerSelector + '-pagination').html(resp.paginationHtml);
      } else {
        $container.html('<div style="padding:20px;text-align:center;color:#888">No data.</div>');
      }
    })
    .fail(function () {
      $container.html('<div style="padding:20px;text-align:center;color:red">Failed to load data.</div>');
    });
}

function goPage(endpoint, page, containerSelector) {
  loadList(endpoint, page, containerSelector);
}

$(document).on('click', '.pag-link', function (e) {
  e.preventDefault();
  const endpoint = $(this).data('endpoint');
  const page = parseInt($(this).data('page') || 1, 10);
  const container = $(this).data('container') || '#list-area';
  loadList(endpoint, page, container);
});
