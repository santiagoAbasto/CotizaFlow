import { test, expect, type Page } from '@playwright/test';

async function verifyResponsive(page: Page, screen: string) {
    for (const width of [1440, 1366, 1024, 768, 375]) {
        await page.setViewportSize({ width, height: 900 });
        await expect(page.locator('h1')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
        if (width <= 768) {
            await expect
                .poll(() =>
                    page.locator('.main-nav .nav-active').evaluate((element) => {
                        const item = element.getBoundingClientRect();
                        const nav = element.parentElement!.getBoundingClientRect();
                        return item.left >= nav.left && item.right <= nav.right;
                    }),
                )
                .toBeTruthy();
            if (screen === 'quote-show' && !(await page.locator('.quote-items-head').isVisible())) {
                await expect(page.locator('.mobile-display-label').first()).toBeVisible();
            }
        }
        await page.screenshot({ path: `test-results/${screen}-${width}.png`, fullPage: true });
    }
    await page.setViewportSize({ width: 1440, height: 900 });
}

test('seller builds and edits a real quote through the commercial flow', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/login');
    await page.getByRole('textbox', { name: 'Email', exact: true }).fill('customer-e2e@example.test');
    await page.getByLabel('Contraseña').fill('E2E-only-not-a-real-account!');
    await page.getByRole('button', { name: 'Iniciar sesión' }).click();
    await page.getByRole('button', { name: /Empresa de prueba A/ }).click();
    await page.getByRole('link', { name: 'Cotizaciones', exact: true }).click();
    await expect(page.getByRole('heading', { name: /Cotizaciones/ })).toBeVisible();
    await page.getByRole('link', { name: 'Nueva cotización' }).first().click();

    await page.getByLabel('Cliente').fill('Taller Andino E2E');
    await page.getByRole('option', { name: /Taller Andino E2E/ }).click();
    await page.getByLabel('Lista de precios').selectOption({ label: 'Mayorista E2E · USD' });
    await page.getByLabel('Agregar producto').fill('SKF-E2E-6204');
    await page.getByRole('option', { name: /SKF-E2E-6204/ }).click();
    await page.getByLabel('Cantidad de Rulemán SKF 6204 E2E').fill('15');
    await page.getByLabel('Agregar producto').fill('WEG-E2E-3HP');
    await page.getByRole('option', { name: /WEG-E2E-3HP/ }).click();
    await page.getByLabel('Cantidad de Motor WEG 3HP E2E').fill('2');
    await expect(page.getByText('USD 550.00', { exact: true }).last()).toBeVisible();
    await verifyResponsive(page, 'quote-create');
    await page.getByRole('button', { name: 'Guardar borrador' }).click();

    await expect(page.getByRole('status')).toHaveText('Cotización guardada como borrador');
    await expect(page.getByRole('heading', { name: /QT-\d{6}/ })).toBeVisible();
    await expect(page.getByText('USD 550.00', { exact: true }).last()).toBeVisible();
    const quoteUrl = page.url();
    await verifyResponsive(page, 'quote-show');

    await page.getByRole('link', { name: 'Editar borrador' }).click();
    await page.getByLabel('Descuento de Motor WEG 3HP E2E').fill('5');
    await expect(page.getByText('USD 535.10', { exact: true }).last()).toBeVisible();
    await page.getByRole('button', { name: 'Guardar borrador' }).click();
    await expect(page.getByRole('status')).toHaveText('Borrador actualizado');
    await expect(page.getByText('USD 535.10', { exact: true }).last()).toBeVisible();

    await expect(page.getByRole('link', { name: 'Ver PDF borrador' })).toBeVisible();
    const draftPdf = await page.request.get(quoteUrl + '/pdf');
    expect(draftPdf.status()).toBe(200);
    expect(draftPdf.headers()['content-type']).toContain('application/pdf');
    expect(draftPdf.headers()['content-disposition']).toContain('cotizacion-QT-');

    await page.getByRole('button', { name: 'Marcar como enviada', exact: true }).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.getByRole('dialog').getByRole('button', { name: 'Marcar como enviada', exact: true }).click();
    await expect(page.getByRole('status')).toHaveText('Cotización marcada como enviada');
    await expect(page.locator('.quote-title .status')).toHaveText('Enviada');
    await expect(page.getByRole('link', { name: 'Editar borrador' })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Ver PDF' })).toBeVisible();

    const issuedPdf = await page.request.get(quoteUrl + '/pdf?download=1');
    expect(issuedPdf.status()).toBe(200);
    expect(issuedPdf.headers()['content-type']).toContain('application/pdf');
    expect(issuedPdf.headers()['content-disposition']).toContain('attachment; filename=cotizacion-QT-');

    await page.getByRole('button', { name: 'Marcar aceptada', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Marcar como aceptada', exact: true }).click();
    await expect(page.getByRole('status')).toHaveText('Cotización marcada como aceptada');
    await expect(page.locator('.quote-title .status')).toHaveText('Aceptada');
    await verifyResponsive(page, 'quote-status');
    await page.getByRole('link', { name: 'Volver a cotizaciones' }).click();
    await verifyResponsive(page, 'quote-list');

    await page.getByRole('link', { name: /Cambiar empresa/ }).click();
    await page.getByRole('button', { name: /Empresa de prueba B/ }).click();
    await expect(page.getByRole('link', { name: /Empresa de prueba B/ })).toBeVisible();
    expect((await page.goto(quoteUrl))?.status()).toBe(404);
    expect(errors).toEqual([]);
});
