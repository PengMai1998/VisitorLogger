<?php
if (!defined('__TYPECHO_ADMIN__')) exit;
?>

<?php
include 'common.php';
include 'header.php';
include 'menu.php';

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$pageSize = 10;

// 获取记录
$db = Typecho_Db::get();
$prefix = $db->getPrefix();
$totalLogs = 0;
$totalPages = 0;

$ip = isset($_POST['ipQuery']) ? $_POST['ipQuery'] : (isset($_GET['ipQuery']) ? $_GET['ipQuery'] : '');
$totalLogs = $db->fetchObject($db->select(array('COUNT(*)' => 'num'))->from($prefix . 'visitor_log')->where('ip LIKE ?', '%' . $ip . '%'))->num;
$totalPages = ceil($totalLogs / $pageSize);

$logs = VisitorLogger_Plugin::getSearchVisitorLogs($page, $pageSize, $ip);

$formattedStartDate = date('Y-m-d H:i:s', strtotime('today'));
$formattedEndDate = date('Y-m-d H:i:s', strtotime('tomorrow') - 1);

?>

<script src="../usr/plugins/VisitorLogger/js/chart.js"></script>

<script>
// global chart
var chartData = {
    countryData: {},
    provinceData: {},
    routeData: {}
};
var countryPieChart, provinceBarChart, routeBarChart;
var chartsInitialized = false;

// function
function initTabSwitching() {
    const tabs = document.querySelectorAll('.tab');
    const contents = document.querySelectorAll('.content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));

            tab.classList.add('active');
            document.getElementById(tab.dataset.tab).classList.add('active');
        });
    });
}

function initDateInputs() {
    const todayLocal = new Date();
    const today = todayLocal.getFullYear() + '-' + 
    String(todayLocal.getMonth() + 1).padStart(2, '0') + '-' + 
    String(todayLocal.getDate()).padStart(2, '0');
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    startDateInput.value = today;
    endDateInput.value = today;
}

function setupDateValidation() {
    document.getElementById('applyDateRange').addEventListener('click', applyDateRange);
    document.getElementById('startDate').addEventListener('change', validateDates);
    document.getElementById('endDate').addEventListener('change', validateDates);
}

function applyDateRange() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const errorMessage = document.getElementById('error-message');

    if (startDate && endDate && startDate > endDate) {
        errorMessage.textContent = '起始日期不能大于结束日期';
    } else {
        errorMessage.textContent = '';
    }
}

function validateDates() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const applyButton = document.getElementById('applyDateRange');
    const errorMessage = document.getElementById('error-message');

    if (startDate && endDate && startDate > endDate) {
        applyButton.disabled = true;
        errorMessage.textContent = '起始日期不能大于结束日期';
    } else {
        applyButton.disabled = false;
        errorMessage.textContent = '';
    }
}

function requestData(startDate, endDate) {
    fetch('../usr/plugins/VisitorLogger/getVisitStatistic.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            startDate: startDate,
            endDate: endDate
        })
    })
    .then(response => response.json())
    .then(data => {
        chartData = data;
        if (chartsInitialized) {
            updateCharts(data.countryData, data.provinceData, data.routeData);
        } else {
            console.warn('Charts are not initialized yet. Data update skipped.');
        }
    })
    .catch(error => console.error('Error:', error));
}

function setupWindowResize() {
    window.addEventListener('resize', function() {
        const width = window.innerWidth;
        updateCharts(chartData.countryData, chartData.provinceData, chartData.routeData);
    });
}

function setupDateRangeButtons() {
    document.getElementById('applyDateRange').addEventListener('click', function() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;

        if (startDate && endDate) {
            const formattedStartDate = startDate + ' 00:00:00';
            const formattedEndDate = endDate + ' 23:59:59';
            requestData(formattedStartDate, formattedEndDate);
        } else {
            alert('请选择开始日期和结束日期');
        }
    });

    document.getElementById('todayStats').addEventListener('click', () => requestForDateRange('today'));
    document.getElementById('yesterdayStats').addEventListener('click', () => requestForDateRange('yesterday'));
    document.getElementById('weekStats').addEventListener('click', () => requestForDateRange('week'));
    document.getElementById('monthStats').addEventListener('click', () => requestForDateRange('month'));
}

function requestForDateRange(range) {
    const dates = getDateRange(range);
    requestData(dates.startDate, dates.endDate);
}

function getDateRange(range) {
    const today = new Date();
    let startDate, endDate;

    switch(range) {
        case 'today':
            startDate = endDate = formatDateToLocal(today);
            break;
        case 'yesterday':
            const yesterday = new Date(today);
            yesterday.setDate(today.getDate() - 1);
            startDate = endDate = formatDateToLocal(yesterday);
            break;
        case 'week':
            endDate = formatDateToLocal(today);
            startDate = formatDateToLocal(new Date(today.setDate(today.getDate() - 7)));
            break;
        case 'month':
            endDate = formatDateToLocal(today);
            startDate = formatDateToLocal(new Date(today.setMonth(today.getMonth() - 1)));
            break;
    }

    return {
        startDate: startDate + ' 00:00:00',
        endDate: endDate + ' 23:59:59'
    };
}

function formatDateToLocal(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function updateCharts(countryData, provinceData, routeData) {
    console.log('Updating charts with new ', { countryData, provinceData, routeData });

    if (!chartsInitialized) {
        console.warn('Charts are not initialized. Cannot update.');
        return;
    }

    if (countryPieChart) {
        console.log('Pie chart exists');
    } else {
        console.log('Pie chart not exists');
    }
    if (countryPieChart) {
        console.log('Updating country pie chart');
        countryPieChart.data.labels = Object.keys(countryData);
        countryPieChart.data.datasets[0].data = Object.values(countryData);
        countryPieChart.update();
    } else {
        console.warn('Country pie chart is not available');
    }

    if (provinceBarChart) {
        console.log('Updating province bar chart');
        updateChartWithScrollbar(provinceBarChart, provinceData);
    } else {
        console.warn('Province bar chart is not available');
    }

    if (routeBarChart) {
        console.log('Updating route bar chart');
        updateChartWithScrollbar(routeBarChart, routeData);
    } else {
        console.warn('Route bar chart is not available');
    }
}

function updateChartWithScrollbar(chart, data) {
    console.log('Updating chart with scrollbar:', chart.canvas.id, data);

    const labels = Object.keys(data);
    const values = Object.values(data);
    
    chart.data.labels = labels;
    chart.data.datasets[0].data = values;
    
    const chartId = chart.canvas.id;
    const chartContainer = chart.canvas.parentNode;
    const minBarWidth = 50; // 每个柱子的最小宽度
    
    function updateChartSize() {
        const parentCard = document.getElementById(chartId === "provinceBarChart" ? 'chart-card-province' : 'chart-card-route');
        const widthParentCard = parentCard.getBoundingClientRect().width;
        console.log('widthParentCard: ', widthParentCard);

        const totalLabelsWidth = labels.length * minBarWidth;
        
        if (widthParentCard === 0) {
            chartContainer.style.width = '100%';
        } else if (totalLabelsWidth > widthParentCard) {
            chartContainer.style.width = `${totalLabelsWidth}px`;
            chartContainer.style.overflowX = 'auto';
        } else {
            chartContainer.style.width = '100%';
            chartContainer.style.overflowX = 'hidden';
        }
        
        chart.options.scales.x.ticks.autoSkip = false;
        chart.options.scales.x.ticks.maxRotation =30;
        chart.options.scales.x.ticks.minRotation = 60;
        
        chart.update();
    }

    updateChartSize();

    const resizeObserver = new ResizeObserver(updateChartSize);
    resizeObserver.observe(chartContainer);

    window.removeEventListener('resize', updateChartSize);
    
    window.addEventListener('resize', updateChartSize);

    console.log('Chart updated:', chart.canvas.id);
}

function initCharts() {
    console.log('Initializing charts');

    var ctxCountry = document.getElementById('countryPieChart').getContext('2d');
    if (ctxCountry) {
        countryPieChart = new Chart(ctxCountry, {
            type: 'pie',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)',
                        'rgba(255, 159, 64, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: ''
                    }
                }
            }
        });
        if (countryPieChart) {
            console.log('countryPieChart exists');
        } else {
            console.log('countryPieChart not exists');
        }
        console.log('Country pie chart initialized');
    } else {
        console.warn('Country pie chart canvas not found');
    }

    var ctxProvince = document.getElementById('provinceBarChart').getContext('2d');
    if (ctxProvince) {
        provinceBarChart = new Chart(ctxProvince, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: '访问数量',
                    data: [],
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: ''
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        console.log('Province bar chart initialized');
    } else {
        console.warn('Province bar chart canvas not found');
    }
    
    var ctxRoute = document.getElementById('routeBarChart').getContext('2d');
    if (ctxRoute) {
        routeBarChart = new Chart(ctxRoute, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: '访问次数',
                    data: [],
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: ''
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            minRotation: 45, // 设置最小旋转角度
                            maxRotation: 90  // 设置最大旋转角度
                        }
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        console.log('Route bar chart initialized');
    } else {
        console.warn('Route bar chart canvas not found');
    }

    chartsInitialized = true;
    console.log('Charts initialization completed');
}

function initPagination() {
    const paginationContainer = document.getElementById('pagination');
    const currentPage = <?php echo $page; ?>;
    const totalPages = <?php echo $totalPages; ?>;
    const ipQuery = '<?php echo $ip; ?>';
    
    const pagination = generatePagination(totalPages, currentPage, ipQuery);
    renderPagination(paginationContainer, pagination, currentPage, ipQuery);
}

function renderPagination(container, pagination, currentPage, ipQuery) {
    container.innerHTML = '';
    pagination.forEach(page => {
        const li = document.createElement('li');
        if (page === '...') {
            li.innerHTML = `<span>${page}</span>`;
        } else if (page === currentPage) {
            li.classList.add('current');
            li.innerHTML = `<a href="?panel=VisitorLogger%2Fpanel.php&page=${page}&ipQuery=${ipQuery}">${page}</a>`;
        } else {
            li.innerHTML = `<a href="?panel=VisitorLogger%2Fpanel.php&page=${page}&ipQuery=${ipQuery}">${page}</a>`;
        }
        container.appendChild(li);
    });
}

function generatePagination(totalPages, currentPage, ipQuery) {
    const maxPagesToShow = 5;
    const pagination = [];
    if (totalPages <= maxPagesToShow) {
        for (let i = 1; i <= totalPages; i++) {
            pagination.push(i);
        }
    } else {
        const half = Math.floor(maxPagesToShow / 2);
        let start = currentPage - half + 1 - maxPagesToShow % 2;
        let end = currentPage + half;
        if (start <= 0) {
            start = 1;
            end = maxPagesToShow;
        } else if (end > totalPages) {
            start = totalPages - maxPagesToShow + 1;
            end = totalPages;
        }
        for (let i = start; i <= end; i++) {
            pagination.push(i);
        }
        if (start > 1) {
            pagination.unshift('...');
            pagination.unshift(1);
        }
        if (end < totalPages) {
            pagination.push('...');
            pagination.push(totalPages);
        }
    }
    return pagination;
}

// DOMContentLoaded
document.addEventListener('DOMContentLoaded', function () {
    initTabSwitching();
    initDateInputs();
    setupDateValidation();
    setupWindowResize();
    setupDateRangeButtons();
    initCharts();
    initPagination();

    const today = new Date();
    const formattedStartDate = formatDateToLocal(today) + ' 00:00:00';
    const formattedEndDate = formatDateToLocal(today) + ' 23:59:59';
    requestData(formattedStartDate, formattedEndDate);

    var menuBar = document.querySelector('.menu-bar');
    var navList = document.getElementById('typecho-nav-list');
    var mainMenuItems = navList.querySelectorAll('.root');
    if (menuBar) {
        menuBar.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.toggle('focus');
            if (!menuBar.classList.contains('focus')) {
                navList.classList.remove('expanded');
                navList.classList.remove('noexpanded');
                mainMenuItems.forEach(item => {
                    item.classList.remove('expanded')
                });
            }
        });

        document.addEventListener('click', function(e) {
            if (!menuBar.contains(e.target) && !navList.contains(e.target)) {
                menuBar.classList.remove('focus');
                navList.classList.remove('expanded');
                navList.classList.remove('noexpanded');
                mainMenuItems.forEach(item => {
                    item.classList.remove('expanded')
                });
            }
        });
    }

    if (menuBar && navList) {
        mainMenuItems.forEach(item => {
            item.addEventListener('click', function(e) {
                item.classList.add('expanded');
                mainMenuItems.forEach(item2 => {
                    if (item !== item2) {
                        item2.classList.remove('expanded');
                    }
                });
                navList.classList.add('expanded');
                e.preventDefault();
                e.stopPropagation(); // 阻止事件冒泡到 document click 事件
            });
            const mainMenuItemsChild = item.querySelectorAll('.child li a');
            mainMenuItemsChild.forEach(child => {
                child.addEventListener('click', function(e) {
                    window.location.href = child;
                });
            });
        });
    }

});

function setDateRange(rangeType) {
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    const currentDate = new Date();
    let startDate, endDate;

    switch(rangeType) {
        case 'today':
            startDate = new Date(currentDate);
            endDate = new Date(currentDate);
            break;
        case 'yesterday':
            startDate = new Date(currentDate);
            startDate.setDate(currentDate.getDate() - 1);
            endDate = new Date(startDate);
            break;
        case 'week':
            startDate = new Date(currentDate);
            startDate.setDate(currentDate.getDate() - 6);
            endDate = new Date(currentDate);
            break;
        case 'month':
            startDate = new Date(currentDate);
            startDate.setMonth(currentDate.getMonth() - 1);
            endDate = new Date(currentDate);
            break;
        case 'diy':
            return;
        default:
            startDate = new Date(currentDate);
            endDate = new Date(currentDate);
    }

    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    startDateInput.value = formatDate(startDate);
    endDateInput.value = formatDate(endDate);

    startDateInput.value = formatDate(startDate);
    endDateInput.value = formatDate(endDate);
}
</script>

<style>
.main {
    padding: 20px;
}
.btn {
    padding: 5px 10px;
    color: white;
    border: none;
    cursor: pointer;
    margin-right: 5px;
    margin-bottom: 5px;
    font-weight: bold;
}
.body.container {
    max-width: 1200px;
    margin: 0 auto;
}
.tabs {
    display: flex;
    border-bottom: 1px solid #ddd;
    margin-bottom: 20px;
    justify-content: left;
}
.tab {
    padding: 10px 20px;
    cursor: pointer;
    border: 1px solid #ddd;
    border-bottom: none;
    background-color: #f9f9f9;
}
.tab.active {
    background-color: #fff;
    font-weight: bold;
}
.content {
    display: none;
}
.content.active {
    display: block;
}
.card {
    background-color: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    padding: 20px;
}
.card h3 {
    margin-top: 0;
}
.charts-container {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}
.chart-card {
    flex: 1;
    min-width: 45%;
    width: 100%;
    overflow-x: scroll;
}
.chart-card canvas {
    height: 400px !important;
}

.chart-card .chart-wrapper canvas{
    height: 400px !important;
}

.typecho-list-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}
.typecho-list-table th, .typecho-list-table td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
}
.typecho-list-table th {
    background-color: #f4f4f4;
}
.typecho-pager ul {
    list-style: none;
    padding: 0 0 40px 0;
    display: flex;
    justify-content: center;
    margin: 20px 0;
}
.typecho-pager li {
    margin: 0 5px;
}
.typecho-pager li.current a {
    font-weight: bold;
}
.btn-danger {
    background-color: #d9534f;
}
.btn-danger:hover {
    background-color: #c9302c;
}
.btn-today {
    background-color: #4CAF50;
}

.btn-today:hover {
    background-color: #45a049;
}

.btn-yesterday {
    background-color: #FF5733;
}

.btn-yesterday:hover {
    background-color: #E74C3C;
}

.btn-week {
    background-color: #3498DB;
}

.btn-week:hover {
    background-color: #2980B9;
}

.btn-month {
    background-color: #F1C40F;
}

.btn-month:hover {
    background-color: #F39C12;
}

.btn-diy {
    padding: 5px 10px;
    background-color: #007bff;
    color: white;
    border: none;
    cursor: pointer;
}
.btn-diy:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
}
.error-message {
    color: red;
    margin-top: 5px;
}

.btn-diy:hover {
    background-color: #7d3c98;
}

.vertical-line {
    display: inline-block;
    width: 1px;
    height: 100%;
    background-color: black;
    margin: 0 30px;
    vertical-align: middle;
}

</style>
<div class="main">
    <div class="body container">
        <h2>访客日志</h2>
        <div class="tabs">
            <div class="tab active" data-tab="tab-logs">访客日志</div>
            <div class="tab" data-tab="tab-stats">访问统计</div>
        </div>
        <div class="content active" id="tab-logs">
            <form style="margin-bottom:10px"method="post" action="?panel=VisitorLogger%2Fpanel.php&page=<?php echo $page; ?>">
                <label for="days">清理超过</label>
                <input type="number" id="days" name="days" min="0" max="30" value="30">
                <label for="days">天的记录：</label>
                <button type="submit" name="clean_up" class="btn btn-danger">清理记录</button>
            </form>
            <form style="margin-bottom:10px" method="post" action="?panel=VisitorLogger%2Fpanel.php&page=<?php echo $page; ?>">
                <label for="ipQuery">模糊查询IP：</label>
                <input type="text" id="ipQuery" name="ipQuery" value="<?php echo isset($_POST['ipQuery']) ? htmlspecialchars($_POST['ipQuery']) : (isset($_GET['ipQuery']) ? htmlspecialchars($_GET['ipQuery']) : ''); ?>">
                <label for="ipQuery">的记录</label>
                <input type="hidden" name="totalPages" value="<?php echo $totalPages; ?>">
                <button type="submit" name="searchLogs" class="btn btn-danger">查询</button>
            </form>
            <table class="typecho-list-table">
                <thead>
                    <tr>
                        <th>IP</th>
                        <th>访问路由</th>
                        <th>访问地点</th>
                        <th>时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($log['ip']); ?></td>
                        <td><?php echo htmlspecialchars(urldecode($log['route'])); ?></td>
                        <td><?php echo htmlspecialchars($log['country']); ?></td>
                        <td><?php echo htmlspecialchars($log['time']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="typecho-pager">
                <ul id="pagination">
                    <!-- JavaScript will dynamically generate the pagination here -->
                </ul>
            </div>
        </div>
        <div class="content" id="tab-stats">
            <div style="margin-bottom: 10px; display: flex">
                <button type="button" id="todayStats" class="btn btn-today" onclick="setDateRange('today')">今日</button>
                <button type="button" id="yesterdayStats" class="btn btn-yesterday" onclick="setDateRange('yesterday')">昨日</button>
                <button type="button" id="weekStats" class="btn btn-week" onclick="setDateRange('week')">近一周</button>
                <button type="button" id="monthStats" class="btn btn-month" onclick="setDateRange('month')">近一月</button>
             </div>
            <form id="date-range-form" style="margin-bottom: 10px">
                <label for="startDate"></label>
                <input type="date" id="startDate" name="startDate" style="width:auto">
                <label for="endDate">-</label>
                <input type="date" id="endDate" name="endDate" style="width:auto">
                <button type="button" id="applyDateRange" class="btn btn-diy">自定义</button>
                <div id="error-message" class="error-message"></div>
            </form>
            <div class="charts-container">
                <div class="chart-card card">
                    <h3 style="text-align: center">各个国家的访问数量</h3>
                    <canvas id="countryPieChart"></canvas>
                </div>
                <div id="chart-card-province" class="chart-card card">
                    <h3 style="text-align: center">各省份的访问数量</h3>
                    <div class="chart-wrapper">
                        <canvas id="provinceBarChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="charts-container">
                <div id="chart-card-route" class="chart-card card">
                    <h3 style="text-align: center">路由访问统计</h3>
                    <div class="chart-wrapper">
                        <canvas id="routeBarChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include 'footer.php';

if (isset($_POST['clean_up'])) {
    $days = intval($_POST['days']);
    VisitorLogger_Plugin::cleanUpOldRecords($days);
    echo "<script>alert('清理操作已完成');</script>";
    header("Location: ?panel=VisitorLogger%2Fpanel.php&page=$page");
    exit;
}
?>