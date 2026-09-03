export type ElevationTool = 'pkexec' | 'sudo'

export function elevationTool(): ElevationTool {
  const configured = process.env['DEV_ELEVATION_TOOL']
  if (configured === 'pkexec' || configured === 'sudo') return configured
  return process.stdin.isTTY ? 'sudo' : 'pkexec'
}
