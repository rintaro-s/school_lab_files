# 画像のURL
$imageUrl = "https://scontent-itm1-1.xx.fbcdn.net/v/t39.30808-6/326570040_3110401969250133_2163029026329463223_n.png?_nc_cat=1&ccb=1-7&_nc_sid=6ee11a&_nc_ohc=qZ6XQrwaOhwQ7kNvwELrfrH&_nc_oc=Adn5HfgA3hy4ceSDCgVQyv0ZrwODqztB-eJh-PkMvgiN_Rm96McL_MxpyVPjIELxM4-ZmD6vSIDqATBqJw4qAABr&_nc_zt=23&_nc_ht=scontent-itm1-1.xx&_nc_gid=SQFa9mcuNQi9ZoD1aIH5jQ&oh=00_AfVkzLVKhhtIv6ccLmQ2e2K646Je2kds-_KD0H9VzuuFNQ&oe=6899E5FC"

# デスクトップのパス
$desktopPath = [Environment]::GetFolderPath("Desktop")

# ダウンロード先のファイル名とパス
$downloadPath = Join-Path -Path $desktopPath -ChildPath "Minna-no-Ginko_original.png"

#画像をダウンロード
Write-Host "画像をダウンロードしています..."
try {
    Invoke-WebRequest -Uri $imageUrl -OutFile $downloadPath -ErrorAction Stop
    Write-Host "ダウンロードが完了しました。"
} catch {
    Write-Host "画像のダウンロードに失敗しました。" -ForegroundColor Red
    return
}

# 画像を100個コピー
Write-Host "画像を100個コピーしています..."
for ($i = 1; $i -le 100; $i++) {
    # ファイル名にコピー番号を追加
    $copyPath = Join-Path -Path $desktopPath -ChildPath "Minna-no-Ginko_$i.png"
    
    Copy-Item -Path $downloadPath -Destination $copyPath -Force
}

Write-Host "すべての処理が完了しました。デスクトップを確認してください。"
