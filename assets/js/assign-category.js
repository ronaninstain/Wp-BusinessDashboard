jQuery(function ($) {
  const $form = $("#assign_category_form");
  const $allCb = $form.find("#cat_all_emps");
  const $emps = $form.find("#cat_employees");
  const $btn = $form.find(".assign-category-btn");
  const $label = $btn.find(".indicator-label");
  const $spinner = $btn.find(".indicator-progress");
  const $success = $form.find(".success-feedback");

  // Toggle employee multiselect
  $allCb
    .on("change", function () {
      $emps.prop("disabled", this.checked);
    })
    .trigger("change");

  $form.on("submit", function (e) {
    e.preventDefault();

    // Clear
    $form.find(".invalid-feedback").text("");
    $success.hide().empty();

    // Loading on this button
    $btn.prop("disabled", true);
    $label.hide();
    $spinner.show();

    // Data
    const data = {
      action: "bd_assign_category",
      category: $form.find("#cat_category").val(),
      duration_days: $form.find("#cat_duration").val(),
      learners: $allCb.prop("checked") ? [] : $emps.val() || [],
    };

    // AJAX
    $.post(
      BD_Ajax.ajax_url,
      data,
      function (resp) {
        if (resp.success) {
          $success.html(resp.data.messages.join("<br>")).show();
          $form[0].reset();
          $allCb.trigger("change");
        } else {
          $.each(resp.data.errors, function (field, msg) {
            if (field === "learners") {
              $emps.siblings(".invalid-feedback").text(msg);
            } else {
              $form
                .find('[name="' + field + '"]')
                .siblings(".invalid-feedback")
                .text(msg);
            }
          });
        }
      },
      "json"
    )
      .fail(function (_, _, err) {
        $success.html("Error: " + err).show();
      })
      .always(function () {
        // Reset button
        $btn.prop("disabled", false);
        $spinner.hide();
        $label.show();
      });
  });
});
