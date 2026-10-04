import { expect, test, type Page } from '@playwright/test';

async function login(page: Page) {
    await page.goto('/login');
    await page.getByRole('textbox', { name: 'Email', exact: true }).fill('customer-e2e@example.test');
    await page.getByLabel('Contraseña').fill('E2E-only-not-a-real-account!');
    await page.getByRole('button', { name: 'Iniciar sesión' }).click();
    await page.getByRole('button', { name: /Empresa de prueba A/ }).click();
    await page.waitForURL('**/customers');
}

test('dashboard surfaces and clears today commercial work', async ({ page }) => {
    await login(page);
    const scheduledAt = new Date();
    scheduledAt.setHours(15, 0, 0, 0);
    const dateForToday = `${scheduledAt.toISOString().slice(0, 10)}T15:00`;

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
    await page.getByRole('button', { name: 'Programar seguimiento' }).click();
    await page.getByLabel('Fecha y hora').fill(dateForToday);
    await page.getByRole('dialog').getByRole('button', { name: 'Programar seguimiento' }).click();

    await page.getByRole('link', { name: 'Dashboard', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Lo que requiere atención.' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Seguimientos programados hoy' })).toBeVisible();
    const todayQuote = page
        .getByRole('region', { name: 'Seguimientos programados hoy' })
        .locator('.dashboard-priority')
        .filter({ hasText: quoteNumber ?? '' });
    await expect(todayQuote).toHaveCount(1);
    await page.getByRole('link', { name: 'Ver todos', exact: true }).click();
    await expect(page).toHaveURL(/\/follow-ups\?timing=today/);

    const row = page
        .getByRole('region', { name: 'Lista de seguimientos' })
        .locator('.follow-up-row')
        .filter({ hasText: quoteNumber ?? '' });
    await row.getByRole('button', { name: 'Completar' }).click();
    await page.getByLabel('Resultado').selectOption('contacted');
    await page.getByRole('dialog').getByRole('button', { name: 'Completar', exact: true }).click();
    await page.getByRole('link', { name: 'Dashboard', exact: true }).click();
    await expect(
        page
            .getByRole('region', { name: 'Seguimientos programados hoy' })
            .locator('.dashboard-priority')
            .filter({ hasText: quoteNumber ?? '' }),
    ).toHaveCount(0);

    for (const width of [1440, 1366, 1024, 768, 375]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
    }
});
