$path = "c:\xampp2\htdocs\slipp\admin\bet_distribution.php"
$tempPath = "c:\xampp2\htdocs\slipp\admin\temp_layout.html"
$content = Get-Content $path
Write-Host "Original Line Count: $($content.Length)"
$newLayout = Get-Content $tempPath
Write-Host "New Layout Line Count: $($newLayout.Length)"

$head = $content[0..1092]
Write-Host "Head Line Count: $($head.Length)"

$tail = $content[1746..($content.Length-1)]
Write-Host "Tail Line Count: $($tail.Length)"

$finalContent = $head + $newLayout + $tail
Write-Host "Final Line Count: $($finalContent.Length)"

$finalContent | Set-Content $path -Encoding UTF8
Write-Host "Written to file."
