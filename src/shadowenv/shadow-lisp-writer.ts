import { existsSync, readFileSync, writeFileSync, appendFileSync } from 'node:fs'
import { UserException } from '../exceptions.js'

export class ShadowLispWriter {
  constructor(private readonly path: string) {
    if (!existsSync(this.path)) {
      throw new UserException(`File ${this.path} does not exist for writing`)
    }
  }

  envSet(key: string, value: string): void {
    const line = `(env/set "${key}" "${value}")`
    const regex = new RegExp(`\\(env/set "${key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}" .*\\)`, 'gm')
    const content = this.content()

    if (!regex.test(content)) {
      this.append(line)
      return
    }

    this.putContent(content.replace(regex, line))
  }

  prependPath(path: string): void {
    const escaped = path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    const regex = new RegExp(`\\(env/prepend-to-pathlist "PATH" "${escaped}"\\)`, 'gm')
    const content = this.content()

    if (regex.test(content)) return

    this.append(`(env/prepend-to-pathlist "PATH" "${path}")`)
  }

  private append(line: string): void {
    appendFileSync(this.path, line + '\n')
  }

  private putContent(content: string): void {
    writeFileSync(this.path, content)
  }

  private content(): string {
    return readFileSync(this.path, 'utf8')
  }
}
