(function(window, document) {
  "use strict";
  if (window.ExamifyPDF) {
    return;
  }
  var THEME = {
    oliveDark: "#3A472A",
    oliveMid: "#5F6F3D",
    oliveTint: "#F0F2E8",
    goldDeep: "#967330",
    goldMid: "#B5924C",
    goldTint: "#FAF4E3",
    white: "#FFFFFF",
    ink: "#2A3022",
    inkMuted: "#6F7860"
  };
  var CDN = {
    html2canvas: "https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js",
    jspdf: "https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"
  };
  function loadScript(src) {
    return new Promise(function(resolve, reject) {
      var s = document.createElement("script");
      s.src = src;
      s.async = true;
      s.onload = resolve;
      s.onerror = function() {
        reject(new Error("Failed to load " + src));
      };
      document.head.appendChild(s);
    });
  }
  function ensureLibs() {
    var needHtml2canvas = typeof window.html2canvas === "undefined";
    var needJsPdf = typeof window.jspdf === "undefined";
    var tasks = [];
    if (needHtml2canvas) tasks.push(loadScript(CDN.html2canvas));
    if (needJsPdf) tasks.push(loadScript(CDN.jspdf));
    return tasks.length ? Promise.all(tasks) : Promise.resolve();
  }
  function formatTimestamp(d) {
    var pad = function(n) {
      return n < 10 ? "0" + n : "" + n;
    };
    var hours = d.getHours();
    var ampm = hours >= 12 ? "PM" : "AM";
    var h12 = hours % 12 === 0 ? 12 : hours % 12;
    var months = [ "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec" ];
    return pad(d.getDate()) + " " + months[d.getMonth()] + " " + d.getFullYear() + ", " + pad(h12) + ":" + pad(d.getMinutes()) + " " + ampm;
  }
  function injectButton(opts) {
    if (document.getElementById("examify-pdf-btn")) return;
    var style = document.createElement("style");
    style.textContent = "#examify-pdf-btn{" + "position:fixed;bottom:24px;right:24px;z-index:999999;" + "display:inline-flex;align-items:center;gap:8px;" + "padding:12px 18px;border-radius:999px;border:1.5px solid " + THEME.goldMid + ";" + "background:" + THEME.oliveDark + ";color:" + THEME.white + ";" + "font-family:Helvetica,Arial,sans-serif;font-size:14px;font-weight:600;" + "box-shadow:0 4px 14px rgba(58,71,42,0.35);cursor:pointer;" + "transition:transform .15s ease, box-shadow .15s ease, background .15s ease;}" + "#examify-pdf-btn:hover{background:" + THEME.oliveMid + ";transform:translateY(-2px);" + "box-shadow:0 6px 18px rgba(58,71,42,0.45);}" + "#examify-pdf-btn:active{transform:translateY(0);}" + "#examify-pdf-btn svg{flex-shrink:0;}" + "#examify-pdf-btn.is-loading{opacity:.75;cursor:progress;pointer-events:none;}" + "#examify-pdf-toast{" + "position:fixed;bottom:80px;right:24px;z-index:999999;" + "background:" + THEME.goldTint + ";color:" + THEME.oliveDark + ";" + "border:1px solid " + THEME.goldMid + ";border-radius:8px;padding:10px 14px;" + "font-family:Helvetica,Arial,sans-serif;font-size:13px;font-weight:600;" + "box-shadow:0 4px 12px rgba(0,0,0,0.15);opacity:0;transform:translateY(6px);" + "transition:opacity .2s ease, transform .2s ease;pointer-events:none;}" + "#examify-pdf-toast.show{opacity:1;transform:translateY(0);}";
    document.head.appendChild(style);
    var btn = document.createElement("button");
    btn.id = "examify-pdf-btn";
    btn.type = "button";
    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' + '<path d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14" stroke="' + THEME.goldTint + '" stroke-width="2" ' + 'stroke-linecap="round" stroke-linejoin="round"/></svg>' + "<span>Download PDF</span>";
    var toast = document.createElement("div");
    toast.id = "examify-pdf-toast";
    document.body.appendChild(toast);
    btn.addEventListener("click", function() {
      window.ExamifyPDF.download(opts, btn, toast);
    });
    document.body.appendChild(btn);
  }
  function showToast(toastEl, message) {
    if (!toastEl) return;
    toastEl.textContent = message;
    toastEl.classList.add("show");
    clearTimeout(toastEl._hideTimer);
    toastEl._hideTimer = setTimeout(function() {
      toastEl.classList.remove("show");
    }, 3200);
  }
  function download(userOpts, btnEl, toastEl) {
    var opts = Object.assign({
      target: "body",
      filename: "Examify-Page",
      title: document.title || "Examify Document",
      subtitle: "Official Academic Assessment Record",
      orientation: "portrait"
    }, userOpts || {});
    var targetEl = typeof opts.target === "string" ? document.querySelector(opts.target) : opts.target;
    if (!targetEl) {
      console.error("[ExamifyPDF] Target element not found:", opts.target);
      showToast(toastEl, "Could not find content to export.");
      return Promise.reject(new Error("Target not found"));
    }
    if (btnEl) {
      btnEl.classList.add("is-loading");
      btnEl.querySelector("span").textContent = "Preparing PDF...";
    }
    return ensureLibs().then(function() {
      return window.html2canvas(targetEl, {
        scale: Math.min(2, window.devicePixelRatio || 1.5),
        useCORS: true,
        backgroundColor: "#ffffff",
        logging: false
      });
    }).then(function(canvas) {
      var jsPDF = window.jspdf.jsPDF;
      var isLandscape = opts.orientation === "landscape";
      var pdf = new jsPDF({
        orientation: isLandscape ? "l" : "p",
        unit: "mm",
        format: "a4"
      });
      var pageW = pdf.internal.pageSize.getWidth();
      var pageH = pdf.internal.pageSize.getHeight();
      var headerH = 24;
      var footerH = 12;
      var marginX = 8;
      var contentTopY = headerH + 4;
      var usableW = pageW - marginX * 2;
      var usableHPerPage = pageH - contentTopY - footerH - 4;
      var imgW = usableW;
      var imgH = canvas.height * imgW / canvas.width;
      var pxPerMm = canvas.width / imgW;
      var pageContentPx = usableHPerPage * pxPerMm;
      var totalPages = Math.max(1, Math.ceil(canvas.height / pageContentPx));
      var timestamp = formatTimestamp(new Date);
      var sliceCanvas = document.createElement("canvas");
      var sliceCtx = sliceCanvas.getContext("2d");
      for (var page = 0; page < totalPages; page++) {
        if (page > 0) pdf.addPage();
        drawHeaderBand(pdf, pageW, headerH, opts.title, opts.subtitle);
        var srcY = page * pageContentPx;
        var sliceHeightPx = Math.min(pageContentPx, canvas.height - srcY);
        var sliceHeightMm = sliceHeightPx / pxPerMm;
        sliceCanvas.width = canvas.width;
        sliceCanvas.height = sliceHeightPx;
        sliceCtx.clearRect(0, 0, sliceCanvas.width, sliceCanvas.height);
        sliceCtx.drawImage(canvas, 0, srcY, canvas.width, sliceHeightPx, 0, 0, canvas.width, sliceHeightPx);
        var sliceData = sliceCanvas.toDataURL("image/jpeg", .95);
        pdf.addImage(sliceData, "JPEG", marginX, contentTopY, imgW, sliceHeightMm);
        drawFooterBand(pdf, pageW, pageH, footerH, timestamp, page + 1, totalPages);
      }
      pdf.save(opts.filename.replace(/[^a-zA-Z0-9_-]/g, "_") + ".pdf");
      if (btnEl) {
        btnEl.classList.remove("is-loading");
        btnEl.querySelector("span").textContent = "Download PDF";
      }
      showToast(toastEl, "PDF downloaded successfully.");
    }).catch(function(err) {
      console.error("[ExamifyPDF] Export failed:", err);
      if (btnEl) {
        btnEl.classList.remove("is-loading");
        btnEl.querySelector("span").textContent = "Download PDF";
      }
      showToast(toastEl, "PDF export failed. See console for details.");
      throw err;
    });
  }
  function hexToRgb(hex) {
    var v = hex.replace("#", "");
    return [ parseInt(v.substring(0, 2), 16), parseInt(v.substring(2, 4), 16), parseInt(v.substring(4, 6), 16) ];
  }
  function drawHeaderBand(pdf, pageW, headerH, title, subtitle) {
    var olive = hexToRgb(THEME.oliveDark);
    var gold = hexToRgb(THEME.goldMid);
    var goldTint = hexToRgb(THEME.goldTint);
    var white = hexToRgb(THEME.white);
    pdf.setFillColor(olive[0], olive[1], olive[2]);
    pdf.rect(0, 0, pageW, headerH, "F");
    pdf.setFillColor(gold[0], gold[1], gold[2]);
    pdf.rect(0, headerH, pageW, 1, "F");
    pdf.setTextColor(white[0], white[1], white[2]);
    pdf.setFont("helvetica", "bold");
    pdf.setFontSize(13);
    pdf.text(String(title || "Examify Document"), pageW / 2, 10, {
      align: "center"
    });
    pdf.setTextColor(goldTint[0], goldTint[1], goldTint[2]);
    pdf.setFont("helvetica", "normal");
    pdf.setFontSize(8.5);
    pdf.text(String(subtitle || ""), pageW / 2, 16, {
      align: "center"
    });
  }
  function drawFooterBand(pdf, pageW, pageH, footerH, timestamp, pageNum, totalPages) {
    var gold = hexToRgb(THEME.goldMid);
    var goldDeep = hexToRgb(THEME.goldDeep);
    var inkMuted = hexToRgb(THEME.inkMuted);
    var lineY = pageH - footerH;
    pdf.setDrawColor(gold[0], gold[1], gold[2]);
    pdf.setLineWidth(.3);
    pdf.line(8, lineY, pageW - 8, lineY);
    pdf.setFont("helvetica", "italic");
    pdf.setFontSize(8);
    pdf.setTextColor(inkMuted[0], inkMuted[1], inkMuted[2]);
    pdf.text("Generated: " + timestamp, 8, pageH - 6);
    pdf.setFont("helvetica", "normal");
    pdf.setTextColor(goldDeep[0], goldDeep[1], goldDeep[2]);
    pdf.text("Page " + pageNum + " of " + totalPages, pageW - 8, pageH - 6, {
      align: "right"
    });
  }
  var ExamifyPDF = {
    download: download,
    init: function(opts) {
      opts = opts || {};
      if (opts.showButton !== false) {
        injectButton(opts);
      }
    }
  };
  window.ExamifyPDF = ExamifyPDF;
  (function autoInit() {
    var currentScript = document.currentScript;
    if (!currentScript || !currentScript.hasAttribute("data-examify-pdf")) return;
    var opts = {
      target: currentScript.getAttribute("data-target") || "body",
      filename: currentScript.getAttribute("data-filename") || "Examify-Page",
      title: currentScript.getAttribute("data-title") || document.title || "Examify Document",
      subtitle: currentScript.getAttribute("data-subtitle") || "Official Academic Assessment Record",
      orientation: currentScript.getAttribute("data-orientation") || "portrait",
      showButton: currentScript.getAttribute("data-no-button") !== "true"
    };
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", function() {
        ExamifyPDF.init(opts);
      });
    } else {
      ExamifyPDF.init(opts);
    }
  })();
})(window, document);