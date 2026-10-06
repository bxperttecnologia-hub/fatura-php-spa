// ==========================
// CONTROLLERS POR ROTA
// ==========================
function initPage(path) {
  const controllers = {
    "/": "views/dashboard.php",
  };

  if (controllers[path]) {
    controllers[path]();
  }
}