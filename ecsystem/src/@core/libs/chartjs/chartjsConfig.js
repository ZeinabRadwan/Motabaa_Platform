import { hexToRgb } from '@layouts/utils'


// 👉 Colors variables
const colorVariables = themeColors => {
  const themeSecondaryTextColor = `rgba(${hexToRgb(themeColors.colors['on-surface'])},${themeColors.variables['high-emphasis-opacity']})`
  const themeDisabledTextColor = `rgba(${hexToRgb(themeColors.colors['on-surface'])},${themeColors.variables['disabled-opacity']})`
  const themeBorderColor = `rgba(${hexToRgb(String(themeColors.variables['border-color']))},${themeColors.variables['border-opacity']})`
  
  return { labelColor: themeDisabledTextColor, borderColor: themeBorderColor, legendColor: themeSecondaryTextColor }
}


// SECTION config
// 👉 Latest Bar Chart Config
export const getLatestBarChartConfig = themeColors => {
  const { borderColor, labelColor } = colorVariables(themeColors)
  
  return {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 500 },
    scales: {
      x: {
        grid: {
          borderColor,
          drawBorder: false,
          color: borderColor,
        },
        ticks: { color: labelColor },
      },
      y: {
        min: 0,
        max: 400,
        grid: {
          borderColor,
          drawBorder: false,
          color: borderColor,
        },
        ticks: {
          stepSize: 100,
          color: labelColor,
        },
      },
    },
    plugins: {
      legend: { display: false },
    },
  }
}

// SECTION config
// 👉 Newest Bar Chart Config
export const getNewestBarChartConfig = themeColors => {
  const { borderColor, labelColor, legendColor } = colorVariables(themeColors)
  
  return {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 500 },
    scales: {
      x: {
        grid: {
          display: false,
          drawBorder: false,
          color: borderColor,
        },
        ticks: { color: legendColor },
      },
      y: {
        grid: {
          display: true,
          drawBorder: false,
          color: borderColor,
        },
        ticks: {
          color: legendColor,
        },
      },
    },
    plugins: {
      legend: { display: false },
    },
  }
}

// 👉 Horizontal Bar Chart Config
export const getHorizontalBarChartConfig = themeColors => {
  const { borderColor, labelColor, legendColor } = colorVariables(themeColors)
  
  return {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 500 },
    elements: {
      bar: {
        borderRadius: {
          topRight: 15,
          bottomRight: 15,
        },
      },
    },
    layout: {
      padding: { top: -4 },
    },
    scales: {
      x: {
        min: 0,
        grid: {
          drawTicks: false,
          drawBorder: false,
          color: borderColor,
        },
        ticks: { color: labelColor },
      },
      y: {
        grid: {
          borderColor,
          display: false,
          drawBorder: false,
        },
        ticks: { color: labelColor },
      },
    },
    plugins: {
      legend: {
        align: 'end',
        position: 'top',
        labels: { color: legendColor },
      },
    },
  }
}

// 👉 Line Chart Config
export const getLineChartConfig = themeColors => {
  const { borderColor, labelColor, legendColor } = colorVariables(themeColors)
  
  return {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      x: {
        ticks: { color: labelColor },
        grid: {
          borderColor,
          drawBorder: false,
          color: borderColor,
        },
      },
      y: {
        min: 0,
        max: 400,
        ticks: {
          stepSize: 100,
          color: labelColor,
        },
        grid: {
          borderColor,
          drawBorder: false,
          color: borderColor,
        },
      },
    },
    plugins: {
      legend: {
        align: 'end',
        position: 'top',
        labels: {
          padding: 25,
          boxWidth: 10,
          color: legendColor,
          usePointStyle: true,
        },
      },
    },
  }
}

// 👉 NewLine Chart Config
export const getNewLineChartConfig = (themeColors, withLegend=false) => {
  const { borderColor, labelColor, legendColor } = colorVariables(themeColors)
  
  return {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      x: {
        ticks: { color: legendColor },
        grid: {
          borderColor,
          drawBorder: false,
          color: borderColor,
        },
      },
      y: {
        ticks: { color: legendColor, },
        grid: {
          borderColor,
          drawBorder: false,
          color: borderColor,
        },
      },
    },
    plugins: {
      legend: {
        display: withLegend,
        align: 'end',
        position: 'top',
        labels: {
          pointStyle: 'false',
          textDirection: 'rtl',
          padding: 25,
          boxWidth: 5,
          onHover: false,
          color: legendColor,
          usePointStyle: true,
        },
      },
    },
  }
}

// 👉 Radar Chart Config
export const getRadarChartConfig = themeColors => {
  const { borderColor, labelColor, legendColor } = colorVariables(themeColors)
  
  return {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 500 },
    layout: {
      padding: { top: -20 },
    },
    scales: {
      r: {
        ticks: {
          display: false,
          maxTicksLimit: 1,
          color: labelColor,
        },
        grid: { color: borderColor },
        pointLabels: { color: labelColor },
        angleLines: { color: borderColor },
      },
    },
    plugins: {
      legend: {
        position: 'top',
        labels: {
          padding: 25,
          color: legendColor,
        },
      },
    },
  }
}

// 👉 Polar Chart Config
export const getPolarChartConfig = themeColors => {
  const { legendColor } = colorVariables(themeColors)
  
  return {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 500 },
    layout: {
      padding: {
        top: -5,
        bottom: -45,
      },
    },
    scales: {
      r: {
        grid: { display: false },
        ticks: { display: false },
      },
    },
    plugins: {
      legend: {
        position: 'right',
        labels: {
          padding: 25,
          boxWidth: 9,
          color: legendColor,
          usePointStyle: true,
        },
      },
    },
  }
}

// 👉 Bubble Chart Config
export const getBubbleChartConfig = themeColors => {
  const { borderColor, labelColor } = colorVariables(themeColors)
  
  return {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      x: {
        min: 0,
        max: 140,
        grid: {
          borderColor,
          drawBorder: false,
          color: borderColor,
        },
        ticks: {
          stepSize: 10,
          color: labelColor,
        },
      },
      y: {
        min: 0,
        max: 400,
        grid: {
          borderColor,
          drawBorder: false,
          color: borderColor,
        },
        ticks: {
          stepSize: 100,
          color: labelColor,
        },
      },
    },
    plugins: {
      legend: { display: false },
    },
  }
}

// 👉 Doughnut Chart Config
export const getDoughnutChartConfig = () => {
  return {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 500 },
    cutout: 80,
    plugins: {
      legend: {
        display: false,
      },
    },
  }
}

// 👉 Scatter Chart Config
export const getScatterChartConfig = themeColors => {
  const { borderColor, labelColor, legendColor } = colorVariables(themeColors)
  
  return {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 800 },
    layout: {
      padding: { top: -20 },
    },
    scales: {
      x: {
        min: 0,
        max: 140,
        grid: {
          borderColor,
          drawTicks: false,
          drawBorder: false,
          color: borderColor,
        },
        ticks: {
          stepSize: 10,
          color: labelColor,
        },
      },
      y: {
        min: 0,
        max: 400,
        grid: {
          borderColor,
          drawTicks: false,
          drawBorder: false,
          color: borderColor,
        },
        ticks: {
          stepSize: 100,
          color: labelColor,
        },
      },
    },
    plugins: {
      legend: {
        align: 'start',
        position: 'top',
        labels: {
          padding: 25,
          boxWidth: 9,
          color: legendColor,
          usePointStyle: true,
        },
      },
    },
  }
}

// 👉 Line Area Chart Config
export const getLineAreaChartConfig = themeColors => {
  const { borderColor, labelColor, legendColor } = colorVariables(themeColors)
  
  return {
    responsive: true,
    maintainAspectRatio: false,
    layout: {
      padding: { top: -20 },
    },
    scales: {
      x: {
        grid: {
          borderColor,
          color: 'transparent',
        },
        ticks: { color: labelColor },
      },
      y: {
        min: 0,
        max: 400,
        grid: {
          borderColor,
          color: 'transparent',
        },
        ticks: {
          stepSize: 100,
          color: labelColor,
        },
      },
    },
    plugins: {
      legend: {
        align: 'start',
        position: 'top',
        labels: {
          padding: 25,
          boxWidth: 9,
          color: legendColor,
          usePointStyle: true,
        },
      },
    },
  }
}

// !SECTION
