jQuery(function ($) {
  $("#assign_course_form").on("submit", function (e) {
    e.preventDefault();
    const $form = $(this);
    const $btn = $form.find(".assign-course-btn");
    const $label = $btn.find(".indicator-label");
    const $spinner = $btn.find(".indicator-progress");
    const $success = $form.find(".success-feedback");

    // Clear previous
    $form.find(".invalid-feedback").text("");
    $success.hide().empty();

    // Show loading on *this* button
    $btn.prop("disabled", true);
    $label.hide();
    $spinner.show();

    // AJAX
    $.post(
      BD_Ajax.ajax_url,
      {
        action: "bd_assign_course",
        inputs: $form.serialize(),
      },
      function (resp) {
        if (resp.success) {
          $form[0].reset();
          $success.html(resp.data.messages.join("<br>")).show();
        } else {
          $.each(resp.data.errors, function (field, msg) {
            $form
              .find('[name="' + field + '"]')
              .siblings(".invalid-feedback")
              .text(msg);
          });
        }
      },
      "json"
    )
      .fail(function (_, _, err) {
        $success.html("Error: " + err).show();
      })
      .always(function () {
        // Reset this button
        $btn.prop("disabled", false);
        $spinner.hide();
        $label.show();
      });
  });
});
