(() => {
  function ensureToastContainer() {
    let container = document.getElementById("toast-container");
    if (!container) {
      container = document.createElement("div");
      container.id = "toast-container";
      container.className = "fixed top-4 right-4 z-50 space-y-2";
      document.body.appendChild(container);
    }
    return container;
  }

  function showToast(message, type = "info", duration = 4000) {
    const container = ensureToastContainer();
    const toast = document.createElement("div");
    const colors = {
      success: "bg-green-600",
      error: "bg-red-600",
      warning: "bg-yellow-500",
      info: "bg-slate-900",
    };
    const colorClass = colors[type] || colors.info;
    toast.className = `${colorClass} text-white px-4 py-3 shadow-lg rounded transition-opacity duration-300`;
    toast.innerHTML = String(message).replace(/\n/g, "<br>");
    container.appendChild(toast);

    setTimeout(() => {
      toast.classList.add("opacity-0");
      setTimeout(() => toast.remove(), 300);
    }, duration);
  }

  window.showToast = showToast;
})();
