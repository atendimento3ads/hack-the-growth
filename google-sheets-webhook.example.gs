const SPREADSHEET_ID = '1cOj6ioJXWb8jd3MiU98J-BMBGVelXquAsIJ5Q5-gw3Y';
const SHEET_NAME = 'leads';
const WEBHOOK_SECRET = 'COLE_A_MESMA_CHAVE_DO_LEAD_CONFIG';

function doGet() {
  return jsonOutput({ success: true, message: 'Webhook ativo.' });
}

function doPost(e) {
  try {
    const data = JSON.parse((e.postData && e.postData.contents) || '{}');
    if (data.secret !== WEBHOOK_SECRET) {
      return jsonOutput({ success: false, message: 'Não autorizado.' });
    }

    const sheet = SpreadsheetApp.openById(SPREADSHEET_ID).getSheetByName(SHEET_NAME);
    if (!sheet) {
      throw new Error('Aba "' + SHEET_NAME + '" não encontrada.');
    }

    const row = [
      data.data_utc,
      data.nome,
      data.email,
      data.empresa,
      data.cargo,
      data.whatsapp,
      data.como_conheceu,
      data.material,
      data.pagina,
      data.consentimento,
      data.status,
      data.responsavel,
      data.ultimo_contato
    ].map(safeCell);

    const lock = LockService.getScriptLock();
    lock.waitLock(10000);
    try {
      sheet.appendRow(row);
    } finally {
      lock.releaseLock();
    }

    return jsonOutput({ success: true });
  } catch (error) {
    console.error(error);
    return jsonOutput({ success: false, message: 'Não foi possível salvar o lead.' });
  }
}

function safeCell(value) {
  const text = value == null ? '' : String(value);
  return /^[=+\-@]/.test(text) ? "'" + text : text;
}

function jsonOutput(value) {
  return ContentService
    .createTextOutput(JSON.stringify(value))
    .setMimeType(ContentService.MimeType.JSON);
}
