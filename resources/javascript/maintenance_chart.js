const maintenance_chart = new Chart(
		document.getElementById('maintenance_chart').getContext('2d'),
		{
			type: 'doughnut',
			data: {
				labels: [active, inactive],
				datasets: [{
						data: [domain_active, domain_inactive],
						backgroundColor: [
							maintenance_chart_main_background_color,
							maintenance_chart_sub_background_color
						],
						borderColor: maintenance_chart_border_color,
						borderWidth: maintenance_chart_border_width,
						cutout: chart_cutout
					}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					chart_counter: {
						chart_text: domain_total
					},
					legend: {
						position: 'right',
						reverse: true,
						labels: {
							usePointStyle: true,
							pointStyle: 'rect'
						}
					},
					title: {
						display: true,
						text: label - maintenance
					}
				}
			},
			plugins: [chart_counter],
		}
);
