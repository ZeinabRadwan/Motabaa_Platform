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

/** Horizontal bar — Athar dashboard goal types */
export const getAtharGoalsTypesChartConfig = (themeColors, suggestedMax) => {
  const { borderColor, labelColor, legendColor } = colorVariables(themeColors)

  return {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 450 },
    elements: {
      bar: {
        borderRadius: 8,
        borderSkipped: false,
      },
    },
    layout: {
      padding: { top: 4, right: 8, left: 4, bottom: 0 },
    },
    scales: {
      x: {
        min: 0,
        suggestedMax: suggestedMax || undefined,
        grid: {
          drawTicks: false,
          drawBorder: false,
          color: borderColor,
        },
        ticks: {
          color: legendColor,
          precision: 0,
          font: { size: 11 },
        },
      },
      y: {
        grid: {
          display: false,
          drawBorder: false,
        },
        ticks: {
          color: labelColor,
          font: { size: 12, weight: '500' },
          autoSkip: false,
        },
      },
    },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#0b2d4d',
        titleFont: { size: 12 },
        bodyFont: { size: 13 },
        padding: 10,
        cornerRadius: 8,
      },
    },
  }
}

export function atharGoalTypeBarColors(count) {
  const shades = [
    '#087ed9',
    '#123b66',
    '#066bb8',
    '#0b2d4d',
    '#3d97e3',
    '#0759a5',
  ]

  return Array.from({ length: count }, (_, index) => shades[index % shades.length])
}

/** Donut — Athar dashboard goal type distribution */
export const getAtharDoughnutChartConfig = themeColors => {
  const { legendColor } = colorVariables(themeColors)

  return {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '72%',
    layout: {
      padding: 4,
    },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#0b2d4d',
        titleFont: { size: 12 },
        bodyFont: { size: 13 },
        padding: 10,
        cornerRadius: 8,
        callbacks: {
          label(context) {
            const value = context.parsed ?? context.raw

            return String(value ?? '')
          },
        },
      },
    },
  }
}

/** Filled line / area — Athar dashboard time series (daily) */
export const getAtharAreaLineChartConfig = (themeColors, suggestedMax) => {
  const { borderColor, legendColor } = colorVariables(themeColors)

  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
      intersect: false,
      mode: 'index',
    },
    scales: {
      x: {
        grid: {
          display: false,
          drawBorder: false,
        },
        ticks: {
          color: legendColor,
          maxRotation: 0,
          autoSkip: true,
          maxTicksLimit: 8,
          font: { size: 11 },
        },
      },
      y: {
        min: 0,
        suggestedMax: suggestedMax || undefined,
        grid: {
          borderColor,
          drawBorder: false,
          color: borderColor,
          tickLength: 0,
        },
        ticks: {
          color: legendColor,
          precision: 0,
          maxTicksLimit: 5,
          font: { size: 11 },
        },
      },
    },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#0b2d4d',
        titleFont: { size: 12 },
        bodyFont: { size: 13 },
        padding: 10,
        cornerRadius: 8,
        displayColors: false,
      },
    },
  }
}

/** Line only (no fill) — Athar navy trend */
export const getAtharLineTrendChartConfig = (themeColors, suggestedMax) => {
  const { borderColor, legendColor } = colorVariables(themeColors)

  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
      intersect: false,
      mode: 'index',
    },
    scales: {
      x: {
        grid: {
          display: false,
          drawBorder: false,
        },
        ticks: {
          color: legendColor,
          maxRotation: 0,
          font: { size: 11 },
        },
      },
      y: {
        min: 0,
        suggestedMax: suggestedMax || undefined,
        grid: {
          drawBorder: false,
          color: borderColor,
          tickLength: 0,
        },
        ticks: {
          color: legendColor,
          precision: 0,
          maxTicksLimit: 5,
          font: { size: 11 },
        },
      },
    },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#0b2d4d',
        titleFont: { size: 12 },
        bodyFont: { size: 13 },
        padding: 10,
        cornerRadius: 8,
        displayColors: false,
      },
    },
  }
}

/** Vertical bars — Athar dashboard monthly totals */
export const getAtharMonthlyBarChartConfig = (themeColors, suggestedMax) => {
  const { borderColor, legendColor } = colorVariables(themeColors)

  return {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 450 },
    elements: {
      bar: {
        borderRadius: 8,
        borderSkipped: false,
      },
    },
    scales: {
      x: {
        grid: {
          display: false,
          drawBorder: false,
        },
        ticks: {
          color: legendColor,
          maxRotation: 0,
          font: { size: 11 },
        },
      },
      y: {
        min: 0,
        suggestedMax: suggestedMax || undefined,
        grid: {
          drawBorder: false,
          color: borderColor,
          tickLength: 0,
        },
        ticks: {
          color: legendColor,
          precision: 0,
          maxTicksLimit: 5,
          font: { size: 11 },
        },
      },
    },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#0b2d4d',
        titleFont: { size: 12 },
        bodyFont: { size: 13 },
        padding: 10,
        cornerRadius: 8,
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
