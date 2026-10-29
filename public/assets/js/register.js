const message = document.getElementById('registerMessage');
const showUnavailableMessage = (event) => {
  event.preventDefault();
  message.textContent = 'O registo e a verificação SMS/WhatsApp ainda não estão disponíveis no servidor Node.js.';
  message.classList.remove('d-none');
};

document.getElementById('stepInfo')?.addEventListener('submit', showUnavailableMessage);
document.getElementById('stepCode')?.addEventListener('submit', showUnavailableMessage);
document.getElementById('btnReenviar')?.addEventListener('click', showUnavailableMessage);
