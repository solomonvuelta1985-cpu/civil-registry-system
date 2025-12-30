/**
 * OCR Field Mapper
 * Maps OCR extracted data to actual form field IDs
 * Handles data format conversions
 */

class OCRFieldMapper {
    constructor() {
        // Map OCR field names to actual form field IDs
        this.fieldMapping = {
            'registry_no': 'registry_no',
            'child_first_name': 'child_first_name',
            'child_middle_name': 'child_middle_name',
            'child_last_name': 'child_last_name',
            'child_sex': 'child_sex',
            'child_date_of_birth': 'child_date_of_birth',
            'child_place_of_birth': 'child_place_of_birth',
            'type_of_birth': 'type_of_birth',
            'birth_order': 'birth_order',
            'mother_first_name': 'mother_first_name',
            'mother_middle_name': 'mother_middle_name',
            'mother_last_name': 'mother_last_name',
            'father_first_name': 'father_first_name',
            'father_middle_name': 'father_middle_name',
            'father_last_name': 'father_last_name'
        };

        // Month name to number mapping
        this.monthMap = {
            'JANUARY': '01', 'JAN': '01',
            'FEBRUARY': '02', 'FEB': '02',
            'MARCH': '03', 'MAR': '03',
            'APRIL': '04', 'APR': '04',
            'MAY': '05',
            'JUNE': '06', 'JUN': '06',
            'JULY': '07', 'JUL': '07',
            'AUGUST': '08', 'AUG': '08',
            'SEPTEMBER': '09', 'SEP': '09', 'SEPT': '09',
            'OCTOBER': '10', 'OCT': '10',
            'NOVEMBER': '11', 'NOV': '11',
            'DECEMBER': '12', 'DEC': '12'
        };
    }

    /**
     * Apply OCR data to form fields
     * @param {Object} structuredData - Extracted data from OCR
     * @returns {Object} - Results of field filling
     */
    applyToForm(structuredData) {
        const results = {
            filled: [],
            skipped: [],
            errors: []
        };

        console.log('🎯 Applying OCR data to form fields...');

        for (const [ocrFieldName, value] of Object.entries(structuredData)) {
            if (value === null || value === undefined || ocrFieldName === 'confidence_scores') {
                continue;
            }

            // Get actual form field ID
            const formFieldId = this.fieldMapping[ocrFieldName] || ocrFieldName;
            const formField = document.getElementById(formFieldId);

            if (!formField) {
                console.warn(`⚠️ Form field not found: ${formFieldId}`);
                results.skipped.push({ field: ocrFieldName, reason: 'Field not found' });
                continue;
            }

            try {
                // Convert value based on field type
                const convertedValue = this.convertValue(value, formField);

                // Set the value
                formField.value = convertedValue;

                // Trigger change event
                formField.dispatchEvent(new Event('change', { bubbles: true }));
                formField.dispatchEvent(new Event('input', { bubbles: true }));

                console.log(`✅ Filled ${formFieldId}: "${convertedValue}"`);
                results.filled.push({ field: ocrFieldName, formFieldId, value: convertedValue });

            } catch (error) {
                console.error(`❌ Error filling ${formFieldId}:`, error);
                results.errors.push({ field: ocrFieldName, error: error.message });
            }
        }

        console.log('📊 Fill Results:', results);
        return results;
    }

    /**
     * Convert value based on form field type
     * @param {any} value - Raw value from OCR
     * @param {HTMLElement} field - Form field element
     * @returns {string} - Converted value
     */
    convertValue(value, field) {
        const fieldType = field.type || field.tagName.toLowerCase();

        switch (fieldType) {
            case 'date':
                return this.convertToDateFormat(value);

            case 'select':
            case 'select-one':
                return this.matchSelectOption(value, field);

            case 'number':
                return this.extractNumber(value);

            default:
                return String(value).trim();
        }
    }

    /**
     * Convert date string to YYYY-MM-DD format
     * Handles formats like: "OCTOBER 17, 1999", "17 OCTOBER 1999", "10/17/1999"
     */
    convertToDateFormat(dateStr) {
        if (!dateStr) return '';

        const str = String(dateStr).toUpperCase().trim();

        // Try to match "OCTOBER 17, 1999" or "17 OCTOBER 1999"
        const monthDayYear = str.match(/([A-Z]+)\s+(\d{1,2}),?\s+(\d{4})/);
        if (monthDayYear) {
            const month = this.monthMap[monthDayYear[1]];
            const day = monthDayYear[2].padStart(2, '0');
            const year = monthDayYear[3];
            if (month) {
                return `${year}-${month}-${day}`;
            }
        }

        // Try "DD MONTH YYYY"
        const dayMonthYear = str.match(/(\d{1,2})\s+([A-Z]+)\s+(\d{4})/);
        if (dayMonthYear) {
            const day = dayMonthYear[1].padStart(2, '0');
            const month = this.monthMap[dayMonthYear[2]];
            const year = dayMonthYear[3];
            if (month) {
                return `${year}-${month}-${day}`;
            }
        }

        // Try "MM/DD/YYYY"
        const slashFormat = str.match(/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/);
        if (slashFormat) {
            const month = slashFormat[1].padStart(2, '0');
            const day = slashFormat[2].padStart(2, '0');
            const year = slashFormat[3];
            return `${year}-${month}-${day}`;
        }

        // Already in YYYY-MM-DD format?
        if (/^\d{4}-\d{2}-\d{2}$/.test(str)) {
            return str;
        }

        console.warn('⚠️ Could not parse date:', dateStr);
        return dateStr;
    }

    /**
     * Match value to closest select option
     */
    matchSelectOption(value, selectField) {
        const valueStr = String(value).toUpperCase().trim();
        const fieldId = selectField.id;

        console.log(`🔍 Matching select option for ${fieldId}:`, {
            inputValue: value,
            valueStr: valueStr,
            availableOptions: Array.from(selectField.options).map(o => ({ value: o.value, text: o.text }))
        });

        // Special handling for birth_order field
        if (fieldId === 'birth_order') {
            return this.matchBirthOrder(valueStr, selectField);
        }

        // Find matching option
        for (const option of selectField.options) {
            const optionText = option.text.toUpperCase().trim();
            const optionValue = option.value.toUpperCase().trim();

            // Skip empty options
            if (optionValue === '' || optionText === '-- SELECT TYPE --' || optionText.startsWith('-- SELECT')) {
                continue;
            }

            // Exact match
            if (optionValue === valueStr || optionText === valueStr) {
                console.log(`  ✅ Exact match found: "${option.value}"`);
                return option.value;
            }

            // Partial match (only if both values are non-empty)
            if (optionValue.length > 0 && valueStr.length > 0) {
                if (optionValue.includes(valueStr) || valueStr.includes(optionValue)) {
                    console.log(`  ✅ Partial match found: "${option.value}"`);
                    return option.value;
                }
            }
        }

        console.warn(`  ⚠️ No match found for "${value}", returning original value`);
        return value;
    }

    /**
     * Match birth order values (handles: 1, First, 1st, etc.)
     */
    matchBirthOrder(value, selectField) {
        const valueStr = String(value).toUpperCase().trim();

        // Map common variations to ordinal format
        const birthOrderMap = {
            '1': '1st', 'FIRST': '1st', '1ST': '1st', 'ONE': '1st',
            '2': '2nd', 'SECOND': '2nd', '2ND': '2nd', 'TWO': '2nd',
            '3': '3rd', 'THIRD': '3rd', '3RD': '3rd', 'THREE': '3rd',
            '4': '4th', 'FOURTH': '4th', '4TH': '4th', 'FOUR': '4th',
            '5': '5th', 'FIFTH': '5th', '5TH': '5th', 'FIVE': '5th',
            '6': '6th', 'SIXTH': '6th', '6TH': '6th', 'SIX': '6th',
            '7': '7th', 'SEVENTH': '7th', '7TH': '7th', 'SEVEN': '7th',
            '8': '8th', 'EIGHTH': '8th', '8TH': '8th', 'EIGHT': '8th',
            '9': '9th', 'NINTH': '9th', '9TH': '9th', 'NINE': '9th',
            '10': '10th', 'TENTH': '10th', '10TH': '10th', 'TEN': '10th'
        };

        // Check direct mapping
        if (birthOrderMap[valueStr]) {
            console.log(`🔄 Birth order mapped: "${value}" → "${birthOrderMap[valueStr]}"`);
            return birthOrderMap[valueStr];
        }

        // Check if value already matches an option
        for (const option of selectField.options) {
            if (option.value.toUpperCase() === valueStr || option.text.toUpperCase() === valueStr) {
                return option.value;
            }
        }

        // If no match found, return original value
        console.warn(`⚠️ No birth order match for: "${value}"`);
        return value;
    }

    /**
     * Extract number from string
     */
    extractNumber(value) {
        const match = String(value).match(/\d+(\.\d+)?/);
        return match ? match[0] : value;
    }
}

// Make globally available
window.OCRFieldMapper = OCRFieldMapper;
console.log('✓ OCR Field Mapper loaded');
