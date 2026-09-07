import { test, expect, type Page } from '@playwright/test';

async function verifyResponsive(page: Page, screen: string) {
    for (const width of [1440, 1366, 1024, 768, 375]) {
        await page.setViewportSize({ width, height: 900 });
        await expect(page.locator('h1')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
        await page.screenshot({ path: `test-results/${screen}-${width}.png`, fullPage: true });
    }
    await page.setViewportSize({ width: 1440, height: 900 });
}

test('seller creates a product and resolves a price-list override', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    const suffix = Date.now().toString();
    const productName = 'Rulemán SKF 6204 ' + suffix;
    const editedName = 'Rulemán sellado SKF 6204 ' + suffix;
    const sku = 'SKF-6204-' + suffix;
    const listName = 'Mayorista ' + suffix;

    await page.goto('/login');
    await page.getByRole('textbox', { name: 'Email', exact: true }).fill('customer-e2e@example.test');
    await page.getByLabel('Contraseña').fill('E2E-only-not-a-real-account!');
    await page.getByRole('button', { name: 'Iniciar sesión' }).click();
    await page.getByRole('button', { name: /Empresa de prueba A/ }).click();
    await page.getByRole('link', { name: 'Productos', exact: true }).click();
    await expect(page.getByRole('heading', { name: /Productos/ })).toBeVisible();
    await verifyResponsive(page, 'products-list');

    await page.getByRole('link', { name: 'Agregar producto' }).first().click();
    await page.getByLabel('Nombre del producto').fill(productName);
    await page.getByLabel('SKU').fill(sku);
    await page.getByLabel('Unidad').selectOption('piece');
    await page.getByLabel('Precio base').fill('18.40');
    await page.getByLabel('Costo base').fill('12.123456');
    await page.getByLabel('Moneda').selectOption('USD');
    await verifyResponsive(page, 'product-form');
    await page.getByRole('button', { name: 'Guardar producto' }).click();
    await expect(page.getByRole('heading', { name: productName, exact: true })).toBeVisible();
    const productUrl = page.url();

    await page.getByRole('link', { name: 'Listas de precios', exact: true }).click();
    await page.getByRole('link', { name: 'Crear lista' }).first().click();
    await page.getByLabel('Nombre de la lista').fill(listName);
    await page.getByLabel('Moneda').selectOption('USD');
    await page.getByRole('button', { name: 'Crear lista' }).click();
    await expect(page.getByRole('heading', { name: listName, exact: true })).toBeVisible();
    await page.getByLabel('Buscar producto').fill(sku);
    await page.getByRole('button', { name: 'Buscar', exact: true }).click();
    const row = page.locator('.pricing-row').filter({ hasText: productName });
    await row.getByLabel('Precio de lista para ' + productName).fill('16.80');
    await row.getByRole('button', { name: 'Asignar' }).click();
    await expect(page.getByRole('status')).toHaveText('Precio de lista guardado');
    await verifyResponsive(page, 'price-list-detail');

    await row.getByRole('link', { name: productName, exact: true }).click();
    await expect(page.getByRole('heading', { name: productName, exact: true })).toBeVisible();
    await page.getByLabel('Consultar lista').selectOption({ label: listName + ' · USD' });
    await page.getByRole('button', { name: 'Consultar' }).click();
    await expect(page.getByText('USD 16.80', { exact: true })).toBeVisible();
    await expect(page.getByText('Origen: ' + listName + '.', { exact: true })).toBeVisible();

    await page.getByRole('link', { name: 'Editar producto' }).click();
    await page.getByLabel('Nombre del producto').fill(editedName);
    await page.getByRole('button', { name: 'Guardar cambios' }).click();
    await expect(page.getByRole('heading', { name: editedName, exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Desactivar producto' }).click();
    await expect(page.getByText('Inactivo', { exact: true })).toBeVisible();

    await verifyResponsive(page, 'product-detail');

    await page.getByRole('link', { name: /Cambiar empresa/ }).click();
    await page.getByRole('button', { name: /Empresa de prueba B/ }).click();
    await expect(page.getByRole('link', { name: /Empresa de prueba B/ })).toBeVisible();
    const response = await page.goto(productUrl);
    expect(response?.status()).toBe(404);
    expect(errors).toEqual([]);
});
