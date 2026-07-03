<?php
$deskripsi_lower = strtolower("soal latihan kelas 11 dengan judul aksara bali");

$materials = [
    ['title' => 'Matematika'],
    ['title' => 'Tari Pendet'],
    ['title' => 'Aksara Bali'],
    ['title' => 'Soal Latihan Aksara Bali Kelas 11']
];

foreach($materials as $m) {
    $stopwords = ['dan','atau','yang','untuk','dari','dalam','ke','di','pada','tentang','dengan','ini','itu','sebagai','materi','bahasa','kelas','bab','semester','kurikulum','merdeka','revisi'];
    
    $words = explode(" ", strtolower($m['title']));
    $title_keywords = [];
    foreach($words as $w) {
        $w = trim(preg_replace('/[^a-z0-9]/', '', $w));
        if(strlen($w) > 2 && !in_array($w, $stopwords)) { $title_keywords[] = $w; } 
    }

    $req_words = explode(" ", $deskripsi_lower);
    $req_keywords = [];
    foreach($req_words as $w) {
        $w = trim(preg_replace('/[^a-z0-9]/', '', $w));
        if(strlen($w) > 2 && !in_array($w, $stopwords)) { $req_keywords[] = $w; } 
    }

    $pct_title = 0;
    $pct_req = 0;

    if(count($title_keywords) > 0) {
        $matched_count_title = 0;
        foreach($title_keywords as $kw) { 
            if(strpos($deskripsi_lower, $kw) !== false) { 
                $matched_count_title++; 
            } 
        }
        $pct_title = ($matched_count_title / count($title_keywords)) * 100;
        
        if(count($req_keywords) > 0) {
            $matched_count_req = 0;
            $title_lower = strtolower($m['title']);
            foreach($req_keywords as $kw) { 
                if(strpos($title_lower, $kw) !== false) { 
                    $matched_count_req++; 
                } 
            }
            $pct_req = ($matched_count_req / count($req_keywords)) * 100;
        }

        echo "Title: " . $m['title'] . "\n";
        echo "pct_title: $pct_title \n";
        echo "pct_req: $pct_req \n\n";
    }
}
?>
