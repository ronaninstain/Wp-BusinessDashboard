jQuery(function ($) {
  const $indForm = $("#bd_register_individual");
  const $bulkForm = $("#bd_register_bulk");

  function handleResponse($form, resp) {
    console.log("AJAX response:", resp);
    const $succ = $form.find(".success-feedback");
    if (resp.success) {
      // Show messages
      const msgs = resp.data.messages || [];
      $succ.html(msgs.join("<br>")).show();
      alert("Success:\n" + msgs.join("\n"));
      $form[0].reset();
    } else {
      const errs = resp.data.errors || {};
      $.each(errs, (field, msg) => {
        $form
          .find('[name="' + field + '"], #' + field)
          .siblings(".invalid-feedback")
          .text(msg);
      });
      alert("Errors:\n" + Object.values(errs).join("\n"));
    }
  }

  // Individual
  $indForm.on("submit", function (e) {
    e.preventDefault();
    const $f = $(this);
    const $btn = $f.find(".bd-register-btn");
    const $spin = $btn.find(".indicator-progress");
    const $lbl = $btn.find(".indicator-label");

    $f.find(".invalid-feedback").text("");
    $f.find(".success-feedback").hide().empty();

    $btn.prop("disabled", true);
    $lbl.hide();
    $spin.show();

    const learner = $f.find("[name=learner]").val();
    if (!learner) {
      $f.find("[name=learner]")
        .siblings(".invalid-feedback")
        .text("Select an employee");
      $btn.prop("disabled", false);
      $spin.hide();
      $lbl.show();
      return;
    }

    const data = {
      action: "bd_register_individual",
      learner: learner,
    };
    console.log("Sending individual register:", data);

    $.post(
      BD_Ajax.ajax_url,
      data,
      function (resp) {
        handleResponse($f, resp);
      },
      "json"
    )
      .fail(function (xhr, status, err) {
        console.error("AJAX error:", status, err);
        alert("AJAX error: " + err);
      })
      .always(function () {
        $btn.prop("disabled", false);
        $spin.hide();
        $lbl.show();
      });
  });

  // Bulk
  $bulkForm.on("submit", function (e) {
    e.preventDefault();
    const $f = $(this);
    const $btn = $f.find(".bd-register-bulk-btn");
    const $spin = $btn.find(".indicator-progress");
    const $lbl = $btn.find(".indicator-label");

    $f.find(".invalid-feedback").text("");
    $f.find(".success-feedback").hide().empty();

    $btn.prop("disabled", true);
    $lbl.hide();
    $spin.show();

    let learners = $f.find('[name="learners[]"]').val() || [];
    if (learners.length === 0) {
      $f.find("#bulk_learners")
        .siblings(".invalid-feedback")
        .text("Select at least one employee");
      $btn.prop("disabled", false);
      $spin.hide();
      $lbl.show();
      return;
    }

    const data = {
      action: "bd_register_bulk",
      learners: learners,
    };
    console.log("Sending bulk register:", data);

    $.post(
      BD_Ajax.ajax_url,
      data,
      function (resp) {
        handleResponse($f, resp);
      },
      "json"
    )
      .fail(function (xhr, status, err) {
        console.error("AJAX error:", status, err);
        alert("AJAX error: " + err);
      })
      .always(function () {
        $btn.prop("disabled", false);
        $spin.hide();
        $lbl.show();
      });
  });
});
