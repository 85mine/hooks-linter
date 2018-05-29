$(document).ready(function() {
  $(".mrk-line-chart").each(function() {
    var value = $(this).data("value");
    $(this).sparkline(value.split(","), {
      type: "line",
      width: "80",
      height: "35",

      lineColor: "#ffb848"
    });
  });
});
