# 画像のURL
$imageUrl = "https://prtimes.jp/data/corp/72105/ogp/tmp-036b907dbadc1dcf282dff5329d23e00-a83f207cd6a7d26933eb9835f88f2de2.jpg"

# デスクトップのパス
$desktopPath = [Environment]::GetFolderPath("Desktop")

# ダウンロード先のファイル名とパス
$downloadPath = Join-Path -Path $desktopPath -ChildPath "downloaded_image.jpg"

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
    $copyPath = Join-Path -Path $desktopPath -ChildPath "image_copy_$i.jpg"
    Copy-Item -Path $downloadPath -Destination $copyPath -Force
}

Write-Host "すべての処理が完了しました。デスクトップを確認してください。"
