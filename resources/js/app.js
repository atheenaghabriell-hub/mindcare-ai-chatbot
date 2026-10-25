import './bootstrap';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import Chart from 'chart.js/auto';

const moodChartCanvas = document.querySelector('#mood-trend-chart');

if (moodChartCanvas) {
    const moodData = window.mindCareMoodData;

    if (moodData) {
        const moodColors = {
            very_low: '#c56a72',
            low: '#d69a62',
            neutral: '#8b9a91',
            good: '#62a88a',
            very_good: '#38866a',
        };

        new Chart(moodChartCanvas, {
            type: 'line',
            animation: false,
            data: {
                labels: moodData.labels,
                datasets: [{
                    label: 'Self-reported mood intensity',
                    data: moodData.intensities,
                    borderColor: '#438f72',
                    backgroundColor: 'rgba(67, 143, 114, 0.12)',
                    pointBackgroundColor: moodData.moods.map((mood) => moodColors[mood] ?? '#438f72'),
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    tension: 0.25,
                    fill: true,
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label(context) {
                                const mood = moodData.moods[context.dataIndex]
                                    ?.replaceAll('_', ' ')
                                    ?? 'check-in';

                                return `${mood}: ${context.parsed.y}/5`;
                            },
                        },
                    },
                },
                scales: {
                    y: {
                        min: 1,
                        max: 5,
                        ticks: { stepSize: 1 },
                        title: { display: true, text: 'Intensity (1–5)' },
                    },
                    x: {
                        grid: { display: false },
                    },
                },
            },
        });
    }
}
