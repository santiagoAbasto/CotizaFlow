import { test, expect } from '@playwright/test';

test('seller manages a real customer through the browser', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    const name = 'Repuestos del Sur ' + Date.now();
    await page.goto('/login');
    await page.getByRole('textbox', { name: 'Email', exact: true }).fill('customer-e2e@example.test');
    await page.getByLabel('Contraseña').fill('E2E-only-not-a-real-account!');
    await page.getByRole('button', { name: 'Iniciar sesión' }).click();
    await page.getByRole('button', { name: /Empresa de prueba A/ }).click();
    await expect(page.getByRole('heading', { name: /Clientes/ })).toBeVisible();
    await page.getByRole('link', { name: 'Agregar cliente' }).first().click();
    await page.getByLabel('Nombre del cliente').fill(name);
    await page.getByLabel('Email', { exact: true }).fill('compras@example.test');
    await page.getByLabel('Teléfono', { exact: true }).fill('+591 700 12345');
    await page.getByLabel('Condición comercial').fill('Pago a 30 días');
    await page.getByLabel('Notas internas').fill('<script>window.customerXss = true</script>');
    await page.getByRole('button', { name: 'Guardar cliente' }).click();
    await expect(page.getByRole('status')).toHaveText('Cliente creado');
    await expect(page.getByRole('heading', { name, exact: true })).toBeVisible();
    await page.getByRole('link', { name: 'Volver a clientes' }).click();
    await page.getByLabel('Buscar cliente', { exact: true }).fill(name);
    await page.getByRole('button', { name: 'Buscar', exact: true }).click();
    await page.getByRole('link', { name, exact: true }).click();
    await page.getByRole('link', { name: 'Editar cliente' }).click();
    await page.getByLabel('Razón social').fill('Repuestos del Sur SRL');
    await page.getByRole('button', { name: 'Guardar cambios' }).click();
    await expect(page.getByRole('status')).toHaveText('Cliente actualizado');
    await page.getByRole('button', { name: 'Desactivar cliente', exact: true }).click();
    await expect(page.getByText('Inactivo', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Activar cliente', exact: true }).click();
    await expect(page.getByText('Activo', { exact: true })).toBeVisible();
    expect(await page.evaluate(() => (window as unknown as Record<string, unknown>).customerXss)).toBeUndefined();
    for (const width of [1440, 1366, 1024, 768, 375]) {
        await page.setViewportSize({ width, height: 900 });
        for (const destination of ['detail', 'edit', 'list']) {
            if (destination === 'edit') await page.getByRole('link', { name: 'Editar cliente' }).click();
            if (destination === 'list') await page.getByRole('link', { name: 'Clientes', exact: true }).click();
            await expect(page.locator('h1')).toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
            await page.screenshot({ path: 'test-results/customer-' + destination + '-' + width + '.png', fullPage: true });
        }
        await page.getByLabel('Buscar cliente', { exact: true }).fill(name);
        await page.getByRole('button', { name: 'Buscar', exact: true }).click();
        await page.getByRole('link', { name, exact: true }).click();
        await expect(page.getByRole('heading', { name, exact: true })).toBeVisible();
    }
    const customerUrl = page.url();
    await page.getByRole('link', { name: /Cambiar empresa/ }).click();
    await page.getByRole('button', { name: /Empresa de prueba B/ }).click();
    await expect(page.getByRole('link', { name: /Empresa de prueba B/ })).toBeVisible();
    const response = await page.goto(customerUrl);
    expect(response?.status()).toBe(404);
    expect(errors).toEqual([]);
});
