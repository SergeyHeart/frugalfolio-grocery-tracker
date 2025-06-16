<?php
//statistics_content.php
?>
<main class="content-area">
    <h2>Spending Statistics</h2>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <?php // --- Tab Navigation --- ?>
    <div class="tabs-container">
        <ul class="stats-tabs">
            <li class="active"><a href="#monthly-spend-panel" data-tab-target="#monthly-spend-panel" class="tab-link">Monthly Trend</a></li>
            <li><a href="#weekly-spend-panel" data-tab-target="#weekly-spend-panel" class="tab-link">Weekly Trend</a></li>
            <li><a href="#category-table-panel" data-tab-target="#category-table-panel" class="tab-link">Category Breakdown</a></li>
            <li><a href="#top-items-panel" data-tab-target="#top-items-panel" class="tab-link">Top Items (Recent)</a></li>
            <li><a href="#price-change-panel" data-tab-target="#price-change-panel" class="tab-link">Price Changes</a></li>
            <li><a href="#month-compare-panel" data-tab-target="#month-compare-panel" class="tab-link">Month vs Previous</a></li>
        </ul>

        <div class="stats-content">
            <?php // --- Tab Content Panels --- ?>

            <!-- Panel 1: Monthly Spending (Active by default) -->
            <div id="monthly-spend-panel" class="tab-content-panel active">
                <div class="stats-card chart-card">
                    <h3>Monthly Spending Trend</h3>
                    <div class="chart-container" style="height:350px;">
                        <canvas id="monthlySpendingChart"></canvas>
                    </div>
                    <div id="monthlySpendingChartError" class="chart-error-message"></div>
                </div>
            </div>

            <!-- Panel 2: Weekly Spending -->
            <div id="weekly-spend-panel" class="tab-content-panel">
                <div class="stats-card chart-card">
                    <h3>Weekly Spending Trend (Last ~12 Wk.)</h3>
                    <div class="chart-container" style="height:350px;">
                        <canvas id="weeklySpendingChart"></canvas>
                    </div>
                    <div id="weeklySpendingChartError" class="chart-error-message"></div>
                </div>
            </div>

            <!-- Panel 3: Category Breakdown Table -->
            <div id="category-table-panel" class="tab-content-panel">
                <div class="stats-card table-card">
                    <h3>Category Spending Breakdown</h3>
                     <?php if (!empty($categoryDetails)): ?>
                        <table id="category-breakdown-table" class="expenses-table data-table display"> 
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Total Spent</th>
                                        <th>% of Total</th>
                                        <th># Items</th>
                                        <th>Avg. Item Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categoryDetails as $cat): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($cat['category_name']) ?></td>
                                            <td style="text-align: right;">₱<?= number_format($cat['total_spent'], 2) ?></td>
                                            <td style="text-align: right;"><?= number_format($cat['percentage'], 1) ?>%</td>
                                            <td style="text-align: right;"><?= $cat['item_count'] ?></td>
                                            <td style="text-align: right;">₱<?= number_format($cat['average_item_cost'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                 <tfoot>
                                     <tr>
                                         <td style="font-weight:bold;">Overall Total</td>
                                         <td style="text-align: right; font-weight:bold;">₱<?= number_format($overallTotalSpend, 2) ?></td>
                                         <td style="text-align: right; font-weight:bold;">100.0%</td>
                                         <td colspan="2"></td> <?php // Adjusted colspan ?>
                                     </tr>
                                 </tfoot>
                            </table>
                        </div>
                     <?php else: ?>
                        <p>No category spending data available.</p>
                     <?php endif; ?>
                </div>
				
			<!-- Panel 4: Top Items List -->
            <div id="top-items-panel" class="tab-content-panel"> <?php // Renamed ID ?>
                 <div class="stats-card list-card">
                    <h3>Top 10 Items by Spend (Last ~3 Months)</h3> <?php // Updated Title ?>
                    <?php if (!empty($topItemsRecent)): ?>
                        <ol class="dashboard-list top-items-list"> <?php // Reuse styling ?>
                             <?php foreach ($topItemsRecent as $index => $item): ?>
                             <li>
                                 <span class="item-rank"><?= $index + 1 ?>.</span>
                                 <span class="item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                                 <span class="item-total">(₱<?= number_format($item['total_spent'], 2) ?> / <?= $item['purchase_count'] ?> times)</span> <?php // Show total and count ?>
                             </li>
                             <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <p>No spending data available for the recent period.</p>
                    <?php endif; ?>
                </div>
            </div>
			
			<!-- Panel 5: Price Change Panel ** -->
            <div id="price-change-panel" class="tab-content-panel">
                <h3>Price Changes (Per Item, Last ~3 Months)</h3>
                <div class="price-change-container"> <?php // Flex container for two lists ?>
                    
                    <div class="stats-card list-card price-increase-card">
                        <h4>Top 10 Increases</h4>
                        <?php if (!empty($priceIncreases)): ?>
                            <ol class="dashboard-list price-change-list">
                                <?php foreach ($priceIncreases as $item): ?>
                                    <li>
                                        <span class="item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                                        <span class="item-change">
                                            (₱<?= number_format($item['first_price'], 2) ?> <i class="fas fa-long-arrow-alt-right"></i> ₱<?= number_format($item['last_price'], 2) ?>) 
                                            <strong style="color: red;">(+<?= number_format($item['percent_change'], 1) ?>%)</strong> 
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php else: ?>
                            <p>No significant price increases found recently.</p>
                        <?php endif; ?>
                    </div>

                     <div class="stats-card list-card price-decrease-card">
                        <h4>Top 10 Decreases</h4>
                        <?php if (!empty($priceDecreases)): ?>
                            <ol class="dashboard-list price-change-list">
                                <?php foreach ($priceDecreases as $item): ?>
                                     <li>
                                        <span class="item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                                        <span class="item-change">
                                            (₱<?= number_format($item['first_price'], 2) ?> <i class="fas fa-long-arrow-alt-right"></i> ₱<?= number_format($item['last_price'], 2) ?>) 
                                            <strong style="color: green;">(<?= number_format($item['percent_change'], 1) ?>%)</strong> 
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php else: ?>
                             <p>No significant price decreases found recently.</p>
                        <?php endif; ?>
                    </div>

                </div> <?php // End price-change-container ?>
                 <p style="font-size: 0.8em; color: #777; margin-top: 10px;">* Based on items purchased at least twice in the period and priced per unit (not weight-based).</p>
            </div>
			
			<!-- ** Panel 6: Month vs Previous Panel ** -->
             <div id="month-compare-panel" class="tab-content-panel">
                 <h3>Monthly Comparison</h3>
                 <?php if ($comparisonData && $comparisonData['previous_month_label'] !== "No Previous Month Data"): ?>
                    <div class="month-comparison-summary">
                        Total Spend (<?= htmlspecialchars($comparisonData['latest_month_label']) ?>): 
                        <strong>₱<?= number_format($comparisonData['latest_month_total'], 2) ?></strong>
                        <br>
                        Total Spend (<?= htmlspecialchars($comparisonData['previous_month_label']) ?>): 
                        <strong>₱<?= number_format($comparisonData['previous_month_total'], 2) ?></strong>
                        <br>
                        Difference: 
                        <strong style="color: <?= ($comparisonData['difference'] >= 0) ? 'red' : 'green' ?>;">
                            <?= ($comparisonData['difference'] >= 0 ? '+' : '') ?>₱<?= number_format(abs($comparisonData['difference']), 2) ?> 
                            (<?= number_format($comparisonData['percentage_change'], 1) ?>%)
                        </strong>
                    </div>

                    <div class="month-comparison-details">
                        <div class="stats-card list-card">
                            <h4>Top 10 Items - <?= htmlspecialchars($comparisonData['latest_month_label']) ?></h4>
                            <?php if(!empty($comparisonData['latest_month_top_items'])): ?>
                                <ol class="dashboard-list top-items-list">
                                    <?php foreach($comparisonData['latest_month_top_items'] as $index => $item): ?>
                                    <li>
                                        <span class="item-rank"><?= $index + 1 ?>.</span>
                                        <span class="item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                                        <span class="item-total">(₱<?= number_format($item['amount'], 2) ?> / <?= $item['purchase_count'] ?> times)</span>
                                    </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php else: ?>
                                <p>No items found for this month.</p>
                            <?php endif; ?>
                        </div>
                         <div class="stats-card list-card">
                            <h4>Top 10 Items - <?= htmlspecialchars($comparisonData['previous_month_label']) ?></h4>
                             <?php if(!empty($comparisonData['previous_month_top_items'])): ?>
                                <ol class="dashboard-list top-items-list">
                                     <?php foreach($comparisonData['previous_month_top_items'] as $index => $item): ?>
                                     <li>
                                         <span class="item-rank"><?= $index + 1 ?>.</span>
                                         <span class="item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                                         <span class="item-total">(₱<?= number_format($item['amount'], 2) ?> / <?= $item['purchase_count'] ?> times)</span>
                                     </li>
                                     <?php endforeach; ?>
                                </ol>
                             <?php else: ?>
                                 <p>No items found for this month.</p>
                             <?php endif; ?>
                        </div>
                    </div>

                 <?php elseif ($comparisonData && $comparisonData['latest_month_label']): ?>
                     <p>Only data for <?= htmlspecialchars($comparisonData['latest_month_label']) ?> (Total: ₱<?= number_format($comparisonData['latest_month_total'], 2) ?>) is available. Cannot compare.</p>
                 <?php else: ?>
                     <p>Not enough monthly data available for comparison.</p>
                 <?php endif; ?>
             </div>
			 
        </div>


            <?php /* Add more content panels later */ ?>
			
        </div> <!-- end stats-content -->
    </div> <!-- end tabs-container -->

</main>

<?php
?>