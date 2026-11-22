export const contactTypes = ['lead', 'prospect', 'customer', 'partner', 'student', 'alumni'];
export const contactStatuses = ['new', 'contacted', 'qualified', 'converted', 'lost', 'inactive'];
export const contactTextFields = [
    { key: 'first_name', label: 'First name', required: true, autocomplete: 'given-name', max: 100 },
    { key: 'last_name', label: 'Last name', required: true, autocomplete: 'family-name', max: 100 },
    { key: 'email', label: 'Email', type: 'email', autocomplete: 'email', max: 255 },
    { key: 'phone', label: 'Phone', type: 'tel', autocomplete: 'tel', max: 30 },
    { key: 'whatsapp', label: 'WhatsApp number', type: 'tel', max: 30 },
    { key: 'company', label: 'Company', autocomplete: 'organization', max: 255 },
    { key: 'job_title', label: 'Job title', autocomplete: 'organization-title', max: 255 },
    { key: 'source', label: 'Source', placeholder: 'Referral, website, event…', max: 100 },
];
export const addressFields = [
    { key: 'address_line_1', label: 'Address line 1', autocomplete: 'address-line1', max: 255 },
    { key: 'address_line_2', label: 'Address line 2', autocomplete: 'address-line2', max: 255 },
    { key: 'city', label: 'City', autocomplete: 'address-level2', max: 100 },
    { key: 'state', label: 'State / region', autocomplete: 'address-level1', max: 100 },
    { key: 'postal_code', label: 'Postal code', autocomplete: 'postal-code', max: 30 },
    { key: 'country', label: 'Country', autocomplete: 'country-name', max: 100 },
];
export function contactValues(contact = {}) {
    return {
        ...Object.fromEntries([...contactTextFields, ...addressFields].map(({ key }) => [key, contact[key] || ''])),
        marketing_email: !!contact.marketing_email, marketing_whatsapp: !!contact.marketing_whatsapp,
        type: contact.type || 'lead', status: contact.status || 'new', notes: contact.notes || '',
    };
}
export const titleCase = (value) => value.charAt(0).toUpperCase() + value.slice(1);
