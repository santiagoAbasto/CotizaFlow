export type CustomerStatus = 'active' | 'inactive';
export type Customer = {
    id: number; name: string; legal_name: string | null; tax_id: string | null;
    email: string | null; phone: string | null; whatsapp: string | null;
    commercial_condition: string | null; notes: string | null;
    status: CustomerStatus; updated_at: string;
};
export type CustomerFields = { name: string; legal_name: string; tax_id: string; email: string; phone: string; whatsapp: string; commercial_condition: string; notes: string };

export type CatalogStatus = 'active' | 'inactive';
export type Currency = 'BOB' | 'USD' | 'ARS';
export type ProductUnit = 'unit' | 'piece' | 'kg' | 'meter' | 'liter' | 'box';
export type Product = {
    id: number; sku: string; name: string; description?: string | null; category: string | null;
    unit: ProductUnit; unit_label: string; base_cost: string | null; base_price: string;
    display_base_cost: string | null; display_base_price: string; currency: Currency;
    status: CatalogStatus; updated_at: string;
};
export type ProductFields = { sku: string; name: string; description: string; category: string; unit: ProductUnit; base_cost: string; base_price: string; currency: Currency };
export type PriceList = { id: number; name: string; currency: Currency; status: CatalogStatus; prices_count?: number; updated_at?: string };
export type PriceListFields = { name: string; currency: Currency };
export type ResolvedProductPrice = { price: string; display_price: string; currency: Currency; source: 'base_price' | 'price_list'; price_list_id: number | null; price_list_name: string | null };
