export type PromptArgs = {
  prompt: string
  label?: string
  placeholder?: string
  default?: string
  required?: boolean
  hint?: string
  type?: 'password' | 'text'
}

export type RawEnvValue = string | number | boolean | PromptArgs

export type RawCommand = {
  desc?: string
  run: string | string[]
  signature?: string
}

export type RawServeProcess =
  | string
  | {
      run: string
      env?: string | false
      cwd?: string
    }

export type RawServeGroup = Record<string, RawServeProcess>

export type RawServe = RawServeProcess | RawServeGroup

export type RawScript = {
  desc?: string
  name?: string
  run: string | string[]
  cwd?: string
  'met?'?: string
}

export type RawStep = Record<string, unknown> | RawScript

export type RawConfig = {
  name?: string
  up?: RawStep[]
  steps?: RawStep[]
  commands?: Record<string, RawCommand>
  serve?: Record<string, RawServe> | string
  groups?: Record<string, string[]>
  sites?: Record<string, string>
  env?: Record<string, RawEnvValue>
  projects?: string[]
}
