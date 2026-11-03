// Prueba de extremo a extremo en el navegador (con el simulador).
// Uso: node pruebas/e2e-pagos.js <url-pago-qonto> <url-pago-tropipay> <carpeta-capturas>
const { chromium } = require('playwright');

(async () => {
  const [urlQonto, urlTpp, capturas] = process.argv.slice(2);
  const navegador = await chromium.launch();
  const ctx = await navegador.newContext({ viewport: { width: 1100, height: 900 }, deviceScaleFactor: 1 });
  const pagina = await ctx.newPage();
  const ok = (m) => console.log('✔ ' + m);

  // ---------- Qonto
  await pagina.goto(urlQonto);
  await pagina.screenshot({ path: `${capturas}/1-elegir-metodo.png`, fullPage: true });
  const textos = await pagina.locator('.wc_payment_methods > li > label').allInnerTexts();
  console.log('Métodos visibles:', textos);
  await pagina.check('#payment_method_idital_qonto');
  await Promise.all([pagina.waitForURL(/order-received/), pagina.click('#place_order')]);
  ok('Qonto: redirigido a la página del pedido');
  await pagina.screenshot({ path: `${capturas}/2-qonto-link.png`, fullPage: true });
  const enlace = await pagina.locator('.idital-pago__boton').getAttribute('href');
  ok('Qonto: link generado ' + enlace);
  const pestana = await ctx.newPage();
  await pestana.goto(enlace);
  await pestana.click('#pagar');
  await pestana.waitForSelector('text=Pago realizado');
  ok('Qonto: pagado en la pasarela simulada');
  await pagina.waitForSelector('.idital-pago', { state: 'detached', timeout: 30000 });
  ok('Qonto: la página del pedido se ha actualizado sola');
  await pagina.screenshot({ path: `${capturas}/3-qonto-pagado.png`, fullPage: true });

  // ---------- TropiPay
  await pagina.goto(urlTpp);
  await pagina.check('#payment_method_idital_tropipay');
  await Promise.all([pagina.waitForURL(/localhost:8090\/pagar\/tropipay/), pagina.click('#place_order')]);
  ok('TropiPay: redirigido a la pasarela');
  await pagina.screenshot({ path: `${capturas}/4-tropipay-pasarela.png`, fullPage: true });
  await Promise.all([pagina.waitForURL(/order-received/), pagina.click('#pagar')]);
  ok('TropiPay: de vuelta en la tienda');
  await pagina.waitForSelector('.idital-pago', { state: 'detached', timeout: 30000 }).catch(() => {});
  await pagina.screenshot({ path: `${capturas}/5-tropipay-pagado.png`, fullPage: true });

  await navegador.close();
})().catch((e) => { console.error('✘', e.message); process.exit(1); });
