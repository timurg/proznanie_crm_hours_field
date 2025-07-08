<script src="//api.bitrix24.com/api/v1/"></script>
<script src="regionCodes.js?ver=5"></script>
<script>

function normalizePhone(phone) {
    if (!phone) return null;
    // Удаляем все нецифровые символы
    let cleaned = phone.replace(/[^\d]/g, '');
    // Нормализуем начало: +7 или 8 → 7
    if (cleaned.startsWith('8') && cleaned.length >= 11) {
        cleaned = '7' + cleaned.slice(1);
    } else if (cleaned.startsWith('+7') && cleaned.length >= 12) {
        cleaned = '7' + cleaned.slice(2);
    }
    // Проверяем, что это российский номер (начинается с 7, длина 11)
    if (cleaned.startsWith('7') && cleaned.length === 11) {
        return cleaned;
    }
    console.log('Invalid phone format:', phone);
    return null;
}

function getRegionAndTime(phone) {
    const normalized = normalizePhone(phone);
    if (!normalized) {
        return { region: 'Invalid phone format', time: 'no data' };
    }
    // Получаем трёхзначный код после 7 (или 8)
    const code3 = normalized.slice(1, 4);
    const codeData = window.regionCodes.codes[code3];
    if (!codeData || !codeData.r) {
        return { region: 'Unknown region', time: 'no data' };
    }
    // Преобразуем номер в число
    const num = parseInt(normalized.slice(1), 10);
    let foundRegionId = null;
    for (const range of codeData.r) {
        if (num >= range.s && num <= range.e) {
            foundRegionId = range.i;
            break;
        }
    }
    if (!foundRegionId) {
        return { region: 'Unknown region', time: 'no data' };
    }
    const regionInfo = window.regionCodes.regions[foundRegionId];
    if (!regionInfo) {
        return { region: 'Unknown region', time: 'no data' };
    }
    const options = {
        timeZone: regionInfo.timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    };
    const formatter = new Intl.DateTimeFormat('ru-RU', options);
    const time = formatter.format(new Date());
    return { region: regionInfo.name, time: time };
}

BX24.init(function() {
    var placementInfo = BX24.placement.info();
    console.log('Placement Info:', placementInfo);

    var placementOptions = placementInfo.options || {};
    var placement = placementInfo.placement;
    var value = placementOptions.VALUE || '';

    if (placement === 'USERFIELD_TYPE') {
        var entityId = placementOptions.ENTITY_ID;
        var entityValueId = placementOptions.ENTITY_VALUE_ID;
        console.log('Entity ID:', entityId, 'Entity Value ID:', entityValueId);

        if ((entityId === 'CRM_LEAD' || entityId === 'CRM_DEAL') && entityValueId > 0) {
            var method = entityId === 'CRM_LEAD' ? 'crm.lead.list' : 'crm.deal.list';
            console.log('Calling method:', method, 'with filter ID:', entityValueId);

            BX24.callMethod(
                method,
                {
                    filter: { ID: parseInt(entityValueId) },
                    select: ['ID', 'PHONE']
                },
                function(result) {
                    if (result && result.error && result.error()) {
                        console.error('API Error:', result.error(), result.error_description());
                        updateValue('error: ' + result.error_description());
                    } else {
                        var data = result.data();
                        console.log('API Response Data:', data);

                        var phone = null;
                        if (data[0]) {
                            console.log('Entity Data:', data[0]);
                            if (entityId === 'CRM_LEAD') {
                                phone = data[0].PHONE && data[0].PHONE[0] && data[0].PHONE[0].VALUE;
                            } else if (entityId === 'CRM_DEAL') {
                                phone = data[0].PHONE && data[0].PHONE[0] && data[0].PHONE[0].VALUE;
                            }
                            console.log('Extracted Phone from Entity:', phone);
                        } else {
                            console.log('No entity data found for ID:', entityValueId);
                        }

                        // Если не найден телефон, ищем в контактах (и для лида, и для сделки)
                        if (!phone) {
                            if (entityId === 'CRM_DEAL') {
                                console.log('No phone in deal, checking contacts for Deal ID:', entityValueId);
                                BX24.callMethod('crm.deal.contact.items.get', { id: entityValueId }, function(contactResult) {
                                    if (contactResult.error()) {
                                        console.error('Contact Items Error:', contactResult.error(), contactResult.error_description());
                                        updateValue('error: ' + contactResult.error_description());
                                    } else {
                                        var contacts = contactResult.data();
                                        console.log('Contacts for Deal:', contacts);
                                        if (contacts && contacts.length > 0) {
                                            var contactId = contacts[0].CONTACT_ID;
                                            console.log('Fetching contact data for Contact ID:', contactId);
                                            BX24.callMethod('crm.contact.get', { id: contactId }, function(contactData) {
                                                if (contactData.error()) {
                                                    console.error('Contact Data Error:', contactData.error(), contactResult.error_description());
                                                    updateValue('error: ' + contactData.error_description());
                                                } else {
                                                    var contact = contactData.data();
                                                    console.log('Contact Data:', contact);
                                                    phone = contact.PHONE && contact.PHONE[0] && contact.PHONE[0].VALUE;
                                                    console.log('Extracted Phone from Contact:', phone);
                                                    processPhone(phone);
                                                }
                                            });
                                        } else {
                                            console.log('No contacts found for Deal ID:', entityValueId);
                                            updateValue('no data');
                                        }
                                    }
                                });
                            } else if (entityId === 'CRM_LEAD') {
                                // Для лида ищем связанные контакты
                                console.log('No phone in lead, checking contacts for Lead ID:', entityValueId);
                                BX24.callMethod('crm.lead.contact.items.get', { id: entityValueId }, function(contactResult) {
                                    if (contactResult.error()) {
                                        console.error('Contact Items Error:', contactResult.error(), contactResult.error_description());
                                        updateValue('error: ' + contactResult.error_description());
                                    } else {
                                        var contacts = contactResult.data();
                                        console.log('Contacts for Lead:', contacts);
                                        if (contacts && contacts.length > 0) {
                                            var contactId = contacts[0].CONTACT_ID;
                                            console.log('Fetching contact data for Contact ID:', contactId);
                                            BX24.callMethod('crm.contact.get', { id: contactId }, function(contactData) {
                                                if (contactData.error()) {
                                                    console.error('Contact Data Error:', contactData.error(), contactResult.error_description());
                                                    updateValue('error: ' + contactData.error_description());
                                                } else {
                                                    var contact = contactData.data();
                                                    console.log('Contact Data:', contact);
                                                    phone = contact.PHONE && contact.PHONE[0] && contact.PHONE[0].VALUE;
                                                    console.log('Extracted Phone from Contact:', phone);
                                                    processPhone(phone);
                                                }
                                            });
                                        } else {
                                            console.log('No contacts found for Lead ID:', entityValueId);
                                            updateValue('no data');
                                        }
                                    }
                                });
                            } else {
                                processPhone(phone);
                            }
                        } else {
                            processPhone(phone);
                        }
                    }
                }
            );
        } else {
            console.log('Invalid entity or ID:', entityId, entityValueId);
            updateValue(value || 'Invalid entity or ID');
        }
    } else {
        console.log('Unsupported placement type:', placement);
        updateValue('Unsupported placement type: ' + placement);
    }
});

function processPhone(phone) {
    var value;
    if (phone) {
        const { region, time } = getRegionAndTime(phone);
        value = `${region}, ${time}`;
        console.log('Final Value:', value);
        updateValue(value);
    } else {
        console.log('No phone found, setting value to "no data"');
        value = 'no data';
        updateValue(value);
    }
}

function updateValue(value) {
    var placementInfo = BX24.placement.info();
    var mode = placementInfo.options.MODE || 'view';
    console.log('Update Value:', value, 'Mode:', mode);

    // Только режим просмотра
    if (mode !== 'view') {
        document.body.innerHTML = 'Редактирование недоступно';
        return;
    }

    // value может быть строкой или объектом с region, time
    let region = '', date = '', time = '';
    if (typeof value === 'object' && value !== null && 'region' in value && 'time' in value) {
        region = value.region;
        // Ожидаем формат времени: "дд.мм.гггг, чч:мм:сс" или "дд.мм.гггг, чч:мм"
        const timeParts = value.time.split(',');
        if (timeParts.length === 2) {
            date = timeParts[0].trim();
            time = timeParts[1].trim();
        } else {
            date = value.time;
        }
    } else if (typeof value === 'string') {
        // Если строка, пробуем разбить по запятой
        const parts = value.split(',');
        region = parts[0] ? parts[0].trim() : '';
        date = parts[1] ? parts[1].trim() : '';
        time = parts[2] ? parts[2].trim() : '';
    }

    document.body.style.backgroundColor = '#f9fafb';
    document.body.innerHTML =
        '<div style="white-space:pre-line;text-align:left;padding:10px;font-size:1.1em;">'
        + region + '<br>'
        + date + '<br>'
        + '<h2>' + time + '</h2></div>';
}

function setValue(value) {
    console.log('Setting value:', value);
    BX24.placement.call('SET_VALUE', { VALUE: value }, function(result) {
        if (result.error()) {
            console.error('Set Value Error:', result.error_description());
        } else {
            console.log('Value set successfully');
        }
    });
}
</script>