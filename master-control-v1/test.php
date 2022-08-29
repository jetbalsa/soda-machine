<?php
function count_1( &$S, $m, $n )
{
    // table[i] will be storing the number
    // of solutions for value i. We need n+1
    // rows as the table is constructed in
    // bottom up manner using the base case (n = 0)
    $table = array_fill(0, $n + 1, NULl);
 
    // Base case (If given value is 0)
    $table[0] = 1;
 
    // Pick all coins one by one and update
    // the table[] values after the index
    // greater than or equal to the value
    // of the picked coin
    for($i = 0; $i < $m; $i++)
        for($j = $S[$i]; $j <= $n; $j++)
            $table[$j] += $table[$j - $S[$i]];
 
    return $table[$n];
}
$arr = array(25, 10, 5);
$m = sizeof($arr);
$n = 4.25;
$x = count_1($arr, $m, $n);
echo $x;
 