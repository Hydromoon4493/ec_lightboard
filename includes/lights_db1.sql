-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema mydb
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Schema mydb
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `mydb` DEFAULT CHARACTER SET utf8 ;
-- -----------------------------------------------------
-- Schema u413142534_lightshowdb
-- -----------------------------------------------------
USE `mydb` ;

-- -----------------------------------------------------
-- Table `mydb`.`roles`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `mydb`.`roles` (
  `roleID` INT NOT NULL AUTO_INCREMENT,
  `roleName` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`roleID`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `mydb`.`users`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `mydb`.`users` (
  `userID` INT NOT NULL AUTO_INCREMENT,
  `Fname` VARCHAR(45) NOT NULL,
  `Lname` VARCHAR(45) NOT NULL,
  `email` VARCHAR(45) NOT NULL,
  `password` VARCHAR(45) NOT NULL,
  `roleID` INT NULL,
  `roles_roleID` INT NOT NULL,
  PRIMARY KEY (`userID`),
  UNIQUE INDEX `userID_UNIQUE` (`userID` ASC) VISIBLE,
  UNIQUE INDEX `email_UNIQUE` (`email` ASC) VISIBLE,
  CONSTRAINT `fk_userID_roleID`
    FOREIGN KEY (`roleID`)
    REFERENCES `mydb`.`roles` (`roleID`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `mydb`.`customDisplay`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `mydb`.`customDisplay` (
  `cDisplayID` INT NOT NULL AUTO_INCREMENT,
  `userID` INT NOT NULL,
  `name` VARCHAR(45) NOT NULL,
  `display` BLOB NOT NULL,
  PRIMARY KEY (`cDisplayID`),
  INDEX `fk_cDisplay_userID_idx` (`userID` ASC) VISIBLE,
  CONSTRAINT `fk_cDisplay_userID`
    FOREIGN KEY (`userID`)
    REFERENCES `mydb`.`users` (`userID`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `mydb`.`presetDisplay`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `mydb`.`presetDisplay` (
  `pDisplayID` INT NOT NULL,
  `name` VARCHAR(45) NOT NULL,
  `display` BLOB NOT NULL,
  PRIMARY KEY (`pDisplayID`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `mydb`.`schedule`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `mydb`.`schedule` (
  `eventID` INT NOT NULL AUTO_INCREMENT,
  `startEvent` DATETIME NOT NULL,
  `endEvent` DATETIME NOT NULL,
  `cDisplayID` INT NULL,
  `pDisplayID` INT NULL,
  PRIMARY KEY (`eventID`),
  INDEX `fk_event_cDisplay_idx` (`cDisplayID` ASC) VISIBLE,
  INDEX `fk_event_pDisplay_idx` (`pDisplayID` ASC) VISIBLE,
  CONSTRAINT `fk_event_cDisplay`
    FOREIGN KEY (`cDisplayID`)
    REFERENCES `mydb`.`customDisplay` (`cDisplayID`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_event_pDisplay`
    FOREIGN KEY (`pDisplayID`)
    REFERENCES `mydb`.`presetDisplay` (`pDisplayID`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
