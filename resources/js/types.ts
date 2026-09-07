export type CustomerStatus = 'active' | 'inactive';
export type Customer = {
    id: number; name: string; legal_name: string | null; tax_id: string | null;
    email: string | null; phone: string | null; whatsapp: string | null;
    commercial_condition: string | null; notes: string | null;
    status: CustomerStatus; updated_at: string;
};
export type CustomerFields = { name: string; legal_name: string; tax_id: string; email: string; phone: string; whatsapp: string; commercial_condition: string; notes: string };
