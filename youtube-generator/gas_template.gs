/**
 * Studio 222 Music - YouTube Meta Generator
 * Backend Google Apps Script (GAS) テンプレート
 * 
 * 【デプロイ手順】
 * 1. Google スプレッドシートを新規作成します。
 * 2. 拡張機能 > Apps Script を開きます。
 * 3. このコードを Code.gs に貼り付けます。
 * 4. デプロイ > 新しいデプロイ を選択します。
 * 5. 種類の選択で「ウェブアプリ」を選びます。
 * 6. アクセスできるユーザーを「全員」に設定してデプロイします。
 * 7. 発行された「ウェブアプリのURL」を、フロントエンドの app.js に設定してください。
 */

const SHEET_NAME = 'FooterSettings';

// GETリクエスト（フッター設定の読み込み用）
function doGet(e) {
  const sheet = getOrCreateSheet();
  const footerText = sheet.getRange('A2').getValue() || "";
  
  return ContentService.createTextOutput(JSON.stringify({
    status: "success",
    footer: footerText
  })).setMimeType(ContentService.MimeType.JSON);
}

// POSTリクエスト（フッター設定の保存用、CORS対応のためにOPTIONSも受ける場合があります）
function doPost(e) {
  try {
    const postData = JSON.parse(e.postData.contents);
    
    if (postData.action === 'saveFooter' && postData.footerText !== undefined) {
      const sheet = getOrCreateSheet();
      // A1セルに見出し、A2セルに値を保存する想定
      sheet.getRange('A1').setValue('Footer Settings');
      sheet.getRange('A2').setValue(postData.footerText);
      sheet.getRange('B2').setValue(new Date()); // 更新日時
      
      return ContentService.createTextOutput(JSON.stringify({
        status: "success",
        message: "Footer saved successfully."
      })).setMimeType(ContentService.MimeType.JSON);
    }
    
    return ContentService.createTextOutput(JSON.stringify({
      status: "error",
      message: "Invalid action or data."
    })).setMimeType(ContentService.MimeType.JSON);
    
  } catch(error) {
    return ContentService.createTextOutput(JSON.stringify({
      status: "error",
      message: error.toString()
    })).setMimeType(ContentService.MimeType.JSON);
  }
}

// シートが存在しない場合は作成するヘルパー関数
function getOrCreateSheet() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  let sheet = ss.getSheetByName(SHEET_NAME);
  
  if (!sheet) {
    sheet = ss.insertSheet(SHEET_NAME);
    sheet.getRange('A1:B1').setValues([['Footer Settings', 'Last Updated']]);
    sheet.getRange('A1:B1').setFontWeight('bold');
  }
  
  return sheet;
}
