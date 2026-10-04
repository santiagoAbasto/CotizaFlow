import { expect, test, type Page } from '@playwright/test';

async function login(page: Page) {
    await page.goto('/login');
    await page.getByRole('textbox', { name: 'Email', exact: true }).fill('customer-e2e@example.test');
    await page.getByLabel('Contraseña').fill('E2E-only-not-a-real-account!');
    await page.getByRole('button', { name: 'Iniciar sesión' }).click();
    await page.getByRole('button', { name: /Empresa de prueba A/ }).click();
    await page.waitForURL('**/customers');
}

test('seller schedules, completes, and continues a commercial follow-up', async ({ page }) => {
    await login(page);
    const dateFor = (offset: number) => {
        const value = new Date();
        value.setDate(value.getDate() + offset);
        return `${value.toISOString().slice(0, 10)}T10:00`;
    };
    await page.goto('/quotes/create');
    await page.getByLabel('Cliente').fill('Taller Andino E2E');
    await page.getByRole('option', { name: /Taller Andino E2E/ }).click();
    await page.getByLabel('Lista de precios').selectOption({ label: 'Mayorista E2E · USD' });
    await page.getByLabel('Agregar producto').fill('SKF-E2E-6204');
    await page.getByRole('option', { name: /SKF-E2E-6204/ }).click();
    await page.getByRole('button', { name: 'Guardar borrador' }).click();
    const quoteNumber = await page.getByRole('heading', { name: /QT-\d{6}/ }).textContent();
    await page.getByRole('button', { name: 'Marcar como enviada', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Marcar como enviada', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Programar seguimiento' })).toBeVisible();

    await page.getByRole('button', { name: 'Programar seguimiento' }).click();
    await page.getByLabel('Fecha y hora').fill(dateFor(0));
    await page.getByRole('dialog').getByRole('button', { name: 'Programar seguimiento' }).click();
    await expect(page.getByRole('status')).toHaveText('Seguimiento programado');
    await page.getByRole('link', { name: 'Seguimientos', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Seguimientos' })).toBeVisible();
    const followUpList = page.getByRole('region', { name: 'Lista de seguimientos' });
    const currentFollowUp = followUpList
        .locator('.follow-up-row')
        .filter({ hasText: quoteNumber ?? '' })
        .first();
    await expect(currentFollowUp.getByText('Hoy', { exact: true })).toBeVisible();

    await currentFollowUp.getByRole('button', { name: 'Completar' }).click();
    await page.getByLabel('Resultado').selectOption('customer_reviewing');
    await page.getByLabel('Programar próximo seguimiento').check();
    await page.getByLabel('Fecha y hora').last().fill(dateFor(2));
    await page.getByRole('dialog').getByRole('button', { name: 'Completar', exact: true }).click();
    await expect(page.getByRole('status')).toHaveText('Seguimiento completado');
    const nextFollowUp = followUpList
        .locator('.follow-up-row')
        .filter({ hasText: quoteNumber ?? '' })
        .filter({ hasText: 'Próximo' });
    await expect(nextFollowUp).toHaveCount(1);

    await page.setViewportSize({ width: 375, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();

    await page.setViewportSize({ width: 1440, height: 900 });
    await nextFollowUp.getByRole('link', { name: 'Abrir cotización' }).click();
    await page.getByRole('button', { name: 'Marcar aceptada', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Marcar como aceptada', exact: true }).click();
    await expect(page.getByRole('status')).toHaveText('Cotización marcada como aceptada');
    await page.getByRole('link', { name: 'Seguimientos', exact: true }).click();
    await expect(
        page
            .getByRole('region', { name: 'Lista de seguimientos' })
            .locator('.follow-up-row')
            .filter({ hasText: quoteNumber ?? '' }),
    ).toHaveCount(0);
});
