'use strict';

// Only interface state lives here. Order records and arithmetic stay on the server.
let token = '';
let signedIn = false;
const protectedScreens = ['operations', 'create', 'view'];

function showNotice(message) {
  const notice = document.getElementById('notice');
  notice.textContent = message;
  notice.hidden = !message;
}

async function request(endpoint, options = {}) {
  const response = await fetch('../servidor/' + endpoint, { credentials: 'same-origin', ...options });
  const data = await response.json();
  if (!response.ok) throw new Error(data.error || 'The request could not be completed.');
  return data;
}

function updateAccount(user) {
  signedIn = Boolean(user);
  document.getElementById('account').textContent = user ? 'Signed in as ' + user : '';
  document.getElementById('logout').hidden = !user;
}

async function refreshSession() {
  const session = await request('session.php');
  token = session.token;
  updateAccount(session.user);
}

async function navigate() {
  let page = location.hash.slice(1) || 'home';
  if (!document.getElementById(page)?.classList.contains('screen')) page = 'home';
  if (protectedScreens.includes(page) && !signedIn) {
    showNotice('Please sign in to continue.');
    location.hash = 'login';
    return;
  }
  document.querySelectorAll('.screen').forEach(screen => { screen.hidden = screen.id !== page; });
  document.title = document.querySelector('#' + page + ' h1').textContent + ' · Order Management';
  if (page === 'create') await loadProducts();
}

async function loadProducts() {
  const container = document.getElementById('products');
  if (container.querySelector('input')) return;
  try {
    const data = await request('products.php');
    container.replaceChildren();
    data.products.forEach(product => {
      const row = document.createElement('div');
      row.className = 'product-row';
      const label = document.createElement('label');
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.name = 'selected[' + product.id + ']';
      checkbox.value = '1';
      const description = document.createElement('span');
      description.textContent = product.name;
      const price = document.createElement('small');
      price.textContent = product.price;
      description.append(price);
      label.append(checkbox, description);
      const quantity = document.createElement('input');
      quantity.type = 'number';
      quantity.name = 'quantity[' + product.id + ']';
      quantity.min = '1';
      quantity.max = '10000';
      quantity.step = '1';
      quantity.placeholder = 'Qty';
      quantity.setAttribute('aria-label', product.name + ' quantity');
      quantity.disabled = true;
      checkbox.addEventListener('change', () => {
        quantity.disabled = !checkbox.checked;
        quantity.required = checkbox.checked;
        if (checkbox.checked) quantity.focus();
      });
      row.append(label, quantity);
      container.append(row);
    });
    document.getElementById('save-order').disabled = false;
  } catch (error) {
    container.textContent = 'Products could not be loaded.';
    showNotice(error.message);
  }
}

async function authenticate(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const button = form.querySelector('button');
  const data = new FormData(form);
  data.append('token', token);
  button.disabled = true;
  try {
    const session = await request('authenticate.php', { method: 'POST', body: data });
    token = session.token;
    updateAccount(session.user);
    form.reset();
    showNotice('');
    location.hash = 'operations';
  } catch (error) {
    form.reset();
    updateAccount(null);
    showNotice(error.message);
    location.hash = 'home';
  } finally {
    button.disabled = false;
  }
}

async function saveOrder(event) {
  event.preventDefault();
  const form = event.currentTarget;
  if (!form.querySelector('input[type="checkbox"]:checked')) {
    showNotice('Select at least one product to create an order.');
    return;
  }
  const button = document.getElementById('save-order');
  button.disabled = true;
  document.getElementById('order-result').hidden = true;
  showNotice('');
  const data = new FormData(form);
  data.append('token', token);
  try {
    const response = await fetch('../servidor/create-order.php', { method: 'POST', body: data, credentials: 'same-origin' });
    const type = response.headers.get('Content-Type') || '';
    if (type.includes('text/html')) {
      const html = await response.text();
      // Display the server-generated file error with its navigation links.
      const documentFromServer = new DOMParser().parseFromString(html, 'text/html');
      const report = documentFromServer.querySelector('.report');
      if (!report) throw new Error('The server could not save your order.');
      const reportWindow = document.getElementById('order-result');
      reportWindow.replaceChildren(document.importNode(report, true));
      reportWindow.className = 'notice';
      reportWindow.hidden = false;
      return;
    }
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'The order could not be saved.');
    }
    // The successful response contains just the server-formatted total.
    document.getElementById('order-result').innerHTML = '<p class="eyebrow">ORDER SAVED SUCCESSFULLY</p><h2>Total including 21% VAT: <span id="order-total"></span></h2><p>Your order is now available in the order history.</p><a class="text-link" href="../servidor/view-orders.php">View order history →</a>';
    document.getElementById('order-total').textContent = await response.text();
    document.getElementById('order-result').className = 'success';
    document.getElementById('order-result').hidden = false;
    form.reset();
    form.querySelectorAll('input[type="number"]').forEach(input => { input.disabled = true; input.required = false; });
    document.getElementById('order-result').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  } catch (error) {
    showNotice(error.message);
  } finally {
    button.disabled = false;
  }
}

function selectOrderView() {
  const single = document.getElementById('view-option').value === 'one';
  document.getElementById('search-field').hidden = !single;
  document.getElementById('search-id').required = single;
}

async function start() {
  document.getElementById('login-form').addEventListener('submit', authenticate);
  document.getElementById('order-form').addEventListener('submit', saveOrder);
  document.getElementById('operations-form').addEventListener('submit', event => {
    event.preventDefault();
    showNotice('');
    location.hash = document.getElementById('operation').value;
  });
  document.getElementById('view-option').addEventListener('change', selectOrderView);
  document.getElementById('view-form').addEventListener('submit', event => {
    event.preventDefault();
    const url = new URL('../servidor/view-orders.php', location.href);
    if (document.getElementById('view-option').value === 'one') url.searchParams.set('id', document.getElementById('search-id').value);
    location.assign(url);
  });
  document.getElementById('logout').addEventListener('click', async () => {
    const data = new FormData();
    data.append('token', token);
    try {
      await request('logout.php', { method: 'POST', body: data });
      updateAccount(null);
      document.getElementById('order-form').reset();
      document.getElementById('order-result').hidden = true;
      document.getElementById('products').replaceChildren();
      document.getElementById('save-order').disabled = true;
      await refreshSession();
      showNotice('You have signed out.');
      location.hash = 'home';
      await navigate();
    } catch (error) { showNotice(error.message); }
  });
  window.addEventListener('hashchange', navigate);
  try { await refreshSession(); } catch (error) { showNotice('The server is unavailable. Please check that PHP is running and reload the page.'); }
  await navigate();
}

start();
